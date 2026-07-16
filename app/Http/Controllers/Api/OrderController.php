<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Mail\OrderConfirmationMail;
use App\Models\Decant;
use App\Models\Order;
use App\Models\Product;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use MercadoPago\Exceptions\MPApiException;

class OrderController extends Controller
{
    public function __construct(
        protected MercadoPagoService $mercadoPagoService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->with(['items', 'user'])->latest();

        if ($request->user()->hasRole('customer')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return OrderResource::collection($query->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.decant_id' => ['nullable', 'exists:decants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', 'in:mercadopago,cash_on_delivery'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_state' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'mercadopago';
        $isCashOnDelivery = $paymentMethod === 'cash_on_delivery';

        $order = DB::transaction(function () use ($validated, $request, $paymentMethod) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $decant = null;

                if (! empty($item['decant_id'])) {
                    $decant = Decant::query()
                        ->where('id', $item['decant_id'])
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($decant->stock < $item['quantity']) {
                        abort(422, "Stock insuficiente del decant de {$decant->ml}ml para {$product->name}.");
                    }

                    $unitPrice = $decant->price;
                } else {
                    if ($product->stock < $item['quantity']) {
                        abort(422, "Stock insuficiente para {$product->name}.");
                    }

                    $unitPrice = $product->price;
                }

                $lineTotal = $unitPrice * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'decant_id' => $decant?->id,
                    'decant_ml' => $decant?->ml,
                    'product_name' => $decant
                        ? "{$product->name} - Decant {$decant->ml}ml"
                        : $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                ];

                if ($decant) {
                    $decant->decrement('stock', $item['quantity']);
                } else {
                    $product->decrement('stock', $item['quantity']);
                }
            }

            $shippingCost = $validated['shipping_cost'] ?? 0;

            $order = Order::create([
                'order_number' => 'PF-'.strtoupper(Str::random(8)),
                'user_id' => $request->user()?->id,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total' => $subtotal + $shippingCost,
                'customer_name' => $validated['customer_name'],
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'] ?? null,
                'shipping_state' => $validated['shipping_state'] ?? null,
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($orderItems as $orderItem) {
                $order->items()->create($orderItem);
            }

            return $order->load('items');
        });

        if ($isCashOnDelivery) {
            $this->sendConfirmationEmail($order);

            return response()->json([
                'message' => 'Pedido registrado. Pagarás contra entrega.',
                'data' => new OrderResource($order),
                'payment' => null,
            ], 201);
        }

        try {
            $preference = $this->mercadoPagoService->createPreference($order);
        } catch (MPApiException $exception) {
            $this->restoreStock($order);

            $order->delete();

            $details = $exception->getApiResponse()->getContent();
            $message = $details['message'] ?? $exception->getMessage();

            return response()->json([
                'message' => 'No se pudo iniciar el pago con Mercado Pago: '.$message,
                'errors' => $details['cause'] ?? $details,
            ], 422);
        }

        $order->update(['mercadopago_preference_id' => $preference['id']]);

        return response()->json([
            'message' => 'Orden creada. Redirige al checkout de Mercado Pago.',
            'data' => new OrderResource($order),
            'payment' => [
                'preference_id' => $preference['id'],
                'init_point' => $preference['init_point'],
                'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,
            ],
        ], 201);
    }

    private function sendConfirmationEmail(Order $order): void
    {
        if (empty($order->customer_email)) {
            return;
        }

        try {
            Mail::to($order->customer_email)->queue(new OrderConfirmationMail($order));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->decant_id) {
                Decant::query()->where('id', $item->decant_id)->increment('stock', $item->quantity);
            } elseif ($item->product_id) {
                Product::query()->where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }
    }

    public function show(Request $request, Order $order): OrderResource|JsonResponse
    {
        if ($request->user()->hasRole('customer') && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $order->load(['items', 'user']);

        return new OrderResource($order);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
        ]);

        $order->update($validated);

        return response()->json([
            'message' => 'Estado actualizado.',
            'data' => new OrderResource($order->fresh()->load('items')),
        ]);
    }
}
