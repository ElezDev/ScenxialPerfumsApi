<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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

        $order = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock < $item['quantity']) {
                    abort(422, "Stock insuficiente para {$product->name}.");
                }

                $lineTotal = $product->price * $item['quantity'];
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $lineTotal,
                ];

                $product->decrement('stock', $item['quantity']);
            }

            $shippingCost = $validated['shipping_cost'] ?? 0;

            $order = Order::create([
                'order_number' => 'PF-'.strtoupper(Str::random(8)),
                'user_id' => $request->user()?->id,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => 'mercadopago',
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

        try {
            $preference = $this->mercadoPagoService->createPreference($order);
        } catch (MPApiException $exception) {
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::query()
                        ->where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }
            }

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
