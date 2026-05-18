<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $transactionId }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 32px; }
        h1, p { margin: 0 0 12px; }
        .header, .summary { margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 10px 12px; text-align: left; }
        th { background: #f3f4f6; }
        .total { margin-top: 18px; font-size: 1.05rem; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $siteContext->displayLabel() }}</h1>
        <p>Invoice export for transaction {{ $transactionId }}</p>
    </div>

    <div class="summary">
        <p><strong>Customer:</strong> {{ $customer->display_name }}</p>
        <p><strong>Transaction ID:</strong> {{ $transactionId }}</p>
        <p><strong>Payment Date:</strong> {{ $invoiceDate ?: '-' }}</p>
    </div>

    <table>
        <thead>
        <tr>
            <th>Order ID</th>
            <th>Design Name</th>
            <th>Completion Date</th>
            <th>Amount</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($invoiceItems as $billing)
            <tr>
                <td>{{ $billing->order_id }}</td>
                <td>{{ $billing->order?->design_name ?: 'Order #'.$billing->order_id }}</td>
                <td>{{ $billing->order?->completion_date ?: $billing->trandtime ?: '-' }}</td>
                <td>${{ number_format((float) preg_replace('/[^0-9.\-]/', '', (string) ($billing->amount ?: $billing->order?->total_amount)), 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <p class="total">Invoice Total: ${{ number_format($invoiceTotal, 2) }}</p>
</body>
</html>
