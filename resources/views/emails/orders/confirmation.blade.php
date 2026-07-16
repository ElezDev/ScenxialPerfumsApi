@php
    $isCod = $order->payment_method === 'cash_on_delivery';
    $isPaid = $order->payment_status === 'paid';
    $money = fn ($value) => '$ '.number_format((float) $value, 0, ',', '.');
    $addressParts = array_filter([$order->shipping_address, $order->shipping_city, $order->shipping_state]);
    $fullAddress = implode(', ', $addressParts);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedido confirmado</title>
</head>
<body style="margin:0; padding:0; background-color:#f2efe9; font-family:Georgia,'Times New Roman',serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2efe9; padding:32px 12px;">
<tr>
<td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background-color:#ffffff;">

  <!-- Header -->
  <tr>
    <td style="background-color:#0a0a0a; padding:36px 40px; text-align:center;">
      <div style="font-family:Georgia,'Times New Roman',serif; font-size:22px; letter-spacing:4px; color:#c9a961; text-transform:uppercase;">
        {{ config('app.name', 'Scenxial Perfums') }}
      </div>
      <div style="margin-top:6px; font-size:10px; letter-spacing:3px; color:#f5f2ed; text-transform:uppercase; opacity:.7;">
        Alta perfumería
      </div>
    </td>
  </tr>

  <!-- Status banner -->
  @if($isCod)
  <tr>
    <td style="background-color:#c9a961; padding:16px 40px; text-align:center;">
      <span style="color:#0a0a0a; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-family:Helvetica,Arial,sans-serif;">
        Pedido confirmado &middot; Pagas contra entrega
      </span>
    </td>
  </tr>
  @elseif($isPaid)
  <tr>
    <td style="background-color:#1f3d2b; padding:16px 40px; text-align:center;">
      <span style="color:#f5f2ed; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-family:Helvetica,Arial,sans-serif;">
        Pago recibido &middot; Pedido confirmado
      </span>
    </td>
  </tr>
  @else
  <tr>
    <td style="background-color:#c9a961; padding:16px 40px; text-align:center;">
      <span style="color:#f5f2ed; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-family:Helvetica,Arial,sans-serif;">
        Pedido recibido &middot; Pendiente de pago
      </span>
    </td>
  </tr>
  @endif

  <!-- Intro -->
  <tr>
    <td style="padding:40px 40px 8px 40px;">
      <p style="margin:0 0 4px 0; font-size:13px; letter-spacing:1px; color:#9a8f75; text-transform:uppercase; font-family:Helvetica,Arial,sans-serif;">Pedido N.&deg; {{ $order->order_number }}</p>
      <h1 style="margin:0 0 16px 0; font-size:24px; color:#0a0a0a; font-weight:normal;">Gracias por tu compra, {{ explode(' ', $order->customer_name)[0] }}</h1>
      <p style="margin:0; font-size:14px; line-height:1.7; color:#3a3a3a; font-family:Helvetica,Arial,sans-serif;">
        @if($isCod)
          Ya tenemos tu pedido en preparación. Recuerda tener el valor exacto al momento de la entrega.
        @elseif($isPaid)
          Confirmamos tu pago. Estamos preparando tu pedido con mucho cuidado para que llegue en perfecto estado.
        @else
          Registramos tu pedido y estamos a la espera de la confirmación del pago.
        @endif
      </p>
    </td>
  </tr>

  <!-- Items -->
  <tr>
    <td style="padding:24px 40px 0 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e8e3d8;">
        @foreach($order->items as $item)
        <tr>
          <td style="padding:16px 0; border-bottom:1px solid #e8e3d8; font-family:Helvetica,Arial,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:14px; color:#0a0a0a; padding-right:12px;">
                  {{ $item->product_name }}
                  <div style="font-size:12px; color:#9a8f75; margin-top:2px;">Cantidad: {{ $item->quantity }}</div>
                </td>
                <td align="right" style="font-size:14px; color:#0a0a0a; white-space:nowrap;">
                  {{ $money($item->total_price) }}
                </td>
              </tr>
            </table>
          </td>
        </tr>
        @endforeach
      </table>
    </td>
  </tr>

  <!-- Totals -->
  <tr>
    <td style="padding:16px 40px 32px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Helvetica,Arial,sans-serif; font-size:13px; color:#3a3a3a;">
        <tr>
          <td style="padding:4px 0;">Subtotal</td>
          <td align="right" style="padding:4px 0;">{{ $money($order->subtotal) }}</td>
        </tr>
        <tr>
          <td style="padding:4px 0;">Envío</td>
          <td align="right" style="padding:4px 0;">{{ $order->shipping_cost > 0 ? $money($order->shipping_cost) : 'Gratis' }}</td>
        </tr>
        <tr>
          <td style="padding:12px 0 0 0; border-top:1px solid #e8e3d8; font-size:15px; color:#0a0a0a;">Total</td>
          <td align="right" style="padding:12px 0 0 0; border-top:1px solid #e8e3d8; font-size:15px; color:#0a0a0a; font-weight:bold;">{{ $money($order->total) }}</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Shipping / payment info -->
  <tr>
    <td style="padding:0 40px 32px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9f7f2; font-family:Helvetica,Arial,sans-serif;">
        <tr>
          <td style="padding:24px;">
            <p style="margin:0 0 10px 0; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#9a8f75;">Dirección de entrega</p>
            <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#0a0a0a;">
              {{ $fullAddress }}
            </p>
            <p style="margin:0 0 10px 0; font-size:11px; letter-spacing:2px; text-transform:uppercase; color:#9a8f75;">Método de pago</p>
            <p style="margin:0; font-size:14px; color:#0a0a0a;">
              {{ $isCod ? 'Pago contra entrega (efectivo al recibir)' : 'Mercado Pago' }}
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- CTA -->
  <tr>
    <td style="padding:0 40px 40px 40px; text-align:center;">
      <a href="{{ $frontendUrl }}/checkout/resultado?order={{ $order->order_number }}" style="display:inline-block; background-color:#c9a961; color:#0a0a0a; text-decoration:none; padding:14px 36px; font-family:Helvetica,Arial,sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase;">
        Ver mi pedido
      </a>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background-color:#0a0a0a; padding:28px 40px; text-align:center;">
      <p style="margin:0; font-size:11px; letter-spacing:1px; color:#f5f2ed; opacity:.6; font-family:Helvetica,Arial,sans-serif;">
        &copy; {{ date('Y') }} {{ config('app.name', 'Scenxial Perfums') }} &mdash; Alta perfumería
      </p>
      <p style="margin:8px 0 0 0; font-size:11px; color:#c9a961; font-family:Helvetica,Arial,sans-serif;">
        ¿Tienes preguntas sobre tu pedido? Escríbenos por WhatsApp.
      </p>
    </td>
  </tr>

</table>
</td>
</tr>
</table>
</body>
</html>
