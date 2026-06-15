<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body { font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
        .meta { color: #666; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        th { text-align: left; padding: 8px; border-bottom: 2px solid #e5e7eb; font-size: 0.875rem; color: #555; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; }
        .total-row td { font-weight: 600; border-bottom: none; padding-top: 12px; }
        .cta { display: inline-block; margin-top: 1rem; padding: 10px 20px; background: #111; color: #fff; text-decoration: none; border-radius: 6px; font-size: 0.875rem; }
        .footer { margin-top: 2rem; font-size: 0.75rem; color: #999; }
    </style>
</head>
<body>
    <h1>Order Confirmed</h1>
    <p class="meta">Order {{ $order->order_number }} &mdash; {{ $order->created_at->format('F j, Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th style="text-align:right">Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td style="text-align:right">{{ \App\Support\Money::format($item->price * $item->quantity) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td style="text-align:right">{{ \App\Support\Money::format($order->total) }}</td>
            </tr>
        </tbody>
    </table>

    <a href="{{ route('orders.show', $order) }}" class="cta">View Order</a>

    <div class="footer">
        <p>Thank you for your purchase. If you have any questions, please contact support.</p>
    </div>
</body>
</html>
