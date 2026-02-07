<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Receipt</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #333;
            line-height: 1.4;
        }

        .receipt {
            max-width: 100%;
            margin: 0 auto;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
            margin-bottom: 15px;
        }

        .restaurant-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .contact {
            font-size: 8px;
            color: #666;
        }

        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 15px;
            font-size: 8px;
        }

        .info-left,
        .info-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-row {
            margin-bottom: 3px;
        }

        .label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .items-table th {
            background: #333;
            color: white;
            padding: 6px 8px;
            text-align: left;
            font-size: 8px;
        }

        .items-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 8px;
        }

        .order-header {
            background: #f5f5f5;
            font-weight: bold;
            padding: 4px 8px !important;
        }

        .item-notes {
            font-size: 7px;
            color: #666;
            font-style: italic;
            margin-top: 2px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 8px;
        }

        .grand-total {
            border-top: 2px solid #333;
            padding-top: 6px;
            margin-top: 5px;
            font-size: 10px;
            font-weight: bold;
        }

        .payment-section {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
        }

        .payment-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 8px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8px;
        }

        @media print {
            .receipt {
                border: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="restaurant-name">RESTAURANT DELUXE</div>
            <div class="contact">Jl. Sudirman No. 123, Jakarta | (021) 5551234</div>
        </div>

        <!-- Info -->
        <div class="info-section">
            <div class="info-left">
                <div class="info-row">
                    <span class="label">Receipt #</span>
                    <span>{{ str_pad($session->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Customer</span>
                    <span>{{ $session->customer_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Table</span>
                    <span>{{ $session->table->table_number }}</span>
                </div>
            </div>
            <div class="info-right">
                <div class="info-row">
                    <span class="label">Date</span>
                    <span>{{ $session->started_at->format('d/m/Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Time</span>
                    <span>{{ $session->started_at->format('H:i') }} - {{ $session->ended_at->format('H:i') }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Orders</span>
                    <span>{{ $session->orders->count() }}</span>
                </div>
            </div>
        </div>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Item</th>
                    <th style="width: 15%;" class="text-right">Price</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 20%;" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php $itemNumber = 1; @endphp
                @foreach ($session->orders as $order)
                    <tr>
                        <td colspan="5" class="order-header">Order #{{ $order->order_number }}</td>
                    </tr>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="text-center">{{ $itemNumber++ }}</td>
                            <td>
                                {{ $item->menu->name }}
                                @if ($item->notes)
                                    <div class="item-notes">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($item->price, 0) }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">{{ number_format($item->subtotal, 0) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>Rp {{ number_format($session->orders->sum('subtotal'), 0, ',', '.') }}</span>
            </div>
            <div class="total-row">
                <span>Tax (10%)</span>
                <span>Rp {{ number_format($session->orders->sum('tax'), 0, ',', '.') }}</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL</span>
                <span>Rp {{ number_format($session->orders->sum('total'), 0, ',', '.') }}</span>
            </div>
        </div>

        <!-- Payment -->
        <div class="payment-section">
            <div class="payment-title">Payment</div>
            @php
                $paymentsByMethod = $session->orders->flatMap->payments->groupBy('payment_method');
            @endphp
            @foreach ($paymentsByMethod as $method => $payments)
                <div class="payment-row">
                    <span>{{ ucfirst($method) }}</span>
                    <span>Rp {{ number_format($payments->sum('amount'), 0, ',', '.') }}</span>
                </div>
            @endforeach
            @if ($change > 0)
                <div class="payment-row" style="font-weight: bold;">
                    <span>Change</span>
                    <span>Rp {{ number_format($change, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            Thank you for your visit!
        </div>
    </div>
</body>

</html>
