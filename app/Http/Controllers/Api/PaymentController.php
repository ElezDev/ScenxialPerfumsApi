<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected MercadoPagoService $mercadoPagoService
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (($payload['type'] ?? null) === 'payment') {
            $paymentId = $payload['data']['id'] ?? null;

            if ($paymentId) {
                $this->mercadoPagoService->handlePaymentNotification((string) $paymentId);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function status(Order $order): JsonResponse
    {
        return response()->json([
            'order_number' => $order->order_number,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'mercadopago_payment_id' => $order->mercadopago_payment_id,
        ]);
    }
}
