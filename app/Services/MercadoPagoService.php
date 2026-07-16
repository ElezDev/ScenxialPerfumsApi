<?php

namespace App\Services;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    public function __construct()
    {
        $token = config('mercadopago.access_token');

        if (empty($token)) {
            throw new \RuntimeException('Mercado Pago no está configurado. Definí MERCADOPAGO_ACCESS_TOKEN en .env');
        }

        MercadoPagoConfig::setAccessToken($token);
    }

    public function createPreference(Order $order): array
    {
        $client = new PreferenceClient;
        $currency = config('mercadopago.currency', 'COP');

        $items = $order->items->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'title' => mb_substr($item->product_name, 0, 256),
            'quantity' => (int) $item->quantity,
            'unit_price' => $this->formatAmountForCurrency($item->unit_price, $currency),
            'currency_id' => $currency,
        ])->values()->all();

        if ($order->shipping_cost > 0) {
            $items[] = [
                'id' => 'shipping',
                'title' => 'Envío',
                'quantity' => 1,
                'unit_price' => $this->formatAmountForCurrency($order->shipping_cost, $currency),
                'currency_id' => $currency,
            ];
        }

        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
        $resultUrl = "{$frontendUrl}/checkout/resultado?order={$order->order_number}";

        $payload = [
            'items' => $items,
            'payer' => $this->buildPayer($order),
            'external_reference' => (string) $order->id,
            'back_urls' => [
                'success' => $resultUrl,
                'failure' => $resultUrl,
                'pending' => $resultUrl,
            ],
        ];

        if (! $this->isLocalUrl($frontendUrl)) {
            $payload['auto_return'] = 'approved';
        }

        $appUrl = rtrim(config('app.url'), '/');
        if (! $this->isLocalUrl($appUrl)) {
            $payload['notification_url'] = "{$appUrl}/api/payments/webhook";
        }

        try {
            $preference = $client->create($payload);
        } catch (MPApiException $exception) {
            Log::error('MercadoPago preference error', [
                'order_id' => $order->id,
                'status' => $exception->getStatusCode(),
                'response' => $exception->getApiResponse()->getContent(),
            ]);

            throw $exception;
        }

        return [
            'id' => $preference->id,
            'init_point' => $preference->init_point,
            'sandbox_init_point' => $preference->sandbox_init_point ?? null,
        ];
    }

    public function handlePaymentNotification(string $paymentId): void
    {
        try {
            $client = new PaymentClient;
            $payment = $client->get((int) $paymentId);

            $order = Order::query()->find($payment->external_reference);

            if (! $order) {
                return;
            }

            $wasPaid = $order->payment_status === 'paid';

            $order->update([
                'mercadopago_payment_id' => (string) $payment->id,
                'payment_status' => $this->mapPaymentStatus($payment->status),
                'status' => $payment->status === 'approved' ? 'processing' : $order->status,
            ]);

            if (! $wasPaid && $order->payment_status === 'paid' && ! empty($order->customer_email)) {
                Mail::to($order->customer_email)->queue(new OrderConfirmationMail($order->load('items')));
            }
        } catch (\Throwable $exception) {
            Log::error('MercadoPago webhook error', [
                'payment_id' => $paymentId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function buildPayer(Order $order): array
    {
        $payer = [
            'name' => $order->customer_name,
            'email' => $order->customer_email,
        ];

        if (! empty($order->customer_phone)) {
            $payer['phone'] = [
                'number' => preg_replace('/\D+/', '', $order->customer_phone),
            ];
        }

        return $payer;
    }

    protected function formatAmountForCurrency(float|string $amount, string $currency): float
    {
        $value = (float) $amount;

        if (in_array($currency, ['COP', 'CLP', 'PYG'], true)) {
            return (float) max(1, (int) round($value));
        }

        return round($value, 2);
    }

    protected function isLocalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true);
    }

    protected function mapPaymentStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'paid',
            'rejected', 'cancelled' => 'failed',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }
}
