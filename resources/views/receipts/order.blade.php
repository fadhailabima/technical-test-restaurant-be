<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            padding: 30px 40px;
            background: #fff;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 0;
        }

        /* Header Section */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
            position: relative;
        }

        .restaurant-name {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .restaurant-tagline {
            font-size: 12px;
            font-style: italic;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .restaurant-info {
            font-size: 10px;
            margin-top: 10px;
            line-height: 1.6;
            opacity: 0.95;
        }

        /* Receipt Title */
        .receipt-title {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 3px solid #667eea;
        }

        .receipt-title h2 {
            font-size: 18px;
            color: #667eea;
            text-align: center;
            letter-spacing: 1px;
        }

        /* Order Information Section */
        .order-section {
            padding: 20px 30px;
            background: #fafbfc;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e1e4e8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            width: 180px;
            padding: 6px 0;
            font-weight: 600;
            color: #555;
        }

        .info-value {
            display: table-cell;
            padding: 6px 0;
            color: #333;
        }

        .order-number-highlight {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
            letter-spacing: 1px;
        }

        /* Items Table */
        .items-section {
            padding: 20px 30px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .items-table thead {
            background: #667eea;
            color: white;
        }

        .items-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #e1e4e8;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .items-table tbody tr:hover {
            background: #f1f3f5;
        }

        .items-table td {
            padding: 10px;
            vertical-align: top;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .item-notes {
            font-size: 10px;
            color: #6c757d;
            font-style: italic;
            margin-top: 4px;
            padding-left: 10px;
            border-left: 2px solid #dee2e6;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Payment Section */
        .payment-section {
            padding: 20px 30px;
            background: #fafbfc;
            border-top: 2px dashed #dee2e6;
        }

        .payment-methods {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
        }

        .payment-item {
            display: table;
            width: 100%;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f5;
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        .payment-method {
            display: table-cell;
            width: 120px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            font-size: 10px;
        }

        .payment-amount {
            display: table-cell;
            text-align: right;
            color: #333;
        }

        .payment-status {
            display: table-cell;
            width: 100px;
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        /* Totals Section */
        .totals-section {
            padding: 20px 30px;
            background: #fff;
        }

        .totals-table {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
        }

        .totals-table tr {
            border-bottom: 1px solid #f1f3f5;
        }

        .totals-table td {
            padding: 10px 0;
        }

        .totals-label {
            font-weight: 600;
            color: #555;
            width: 200px;
        }

        .totals-amount {
            text-align: right;
            color: #333;
            font-weight: 500;
        }

        .grand-total-row {
            border-top: 3px solid #667eea !important;
            border-bottom: 3px double #667eea !important;
            background: #f8f9fa;
        }

        .grand-total-row td {
            padding: 15px 0 !important;
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
        }

        /* Footer Section */
        .footer {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
            border-top: 3px solid #667eea;
        }

        .thank-you {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }

        .footer-message {
            font-size: 11px;
            opacity: 0.95;
            margin-bottom: 15px;
        }

        .barcode-placeholder {
            background: white;
            color: #333;
            padding: 10px 20px;
            display: inline-block;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            letter-spacing: 2px;
            margin-top: 10px;
        }

        .footer-info {
            margin-top: 15px;
            font-size: 9px;
            opacity: 0.8;
            line-height: 1.6;
        }

        /* Print Optimization */
        @media print {
            body {
                padding: 0;
            }

            .receipt-container {
                border: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="header">
            <div class="restaurant-name">Restaurant Deluxe</div>
            <div class="restaurant-tagline">Fine Dining Experience</div>
            <div class="restaurant-info">
                Jl. Sudirman No. 123, Jakarta Pusat 10220<br>
                Phone: (021) 5551234 | Email: info@restaurantdeluxe.com<br>
                NPWP: 01.234.567.8-901.000
            </div>
        </div>

        <!-- Receipt Title -->
        <div class="receipt-title">
            <h2>PAYMENT RECEIPT</h2>
        </div>

        <!-- Order Information -->
        <div class="order-section">
            <div class="section-title">Order Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Order Number:</div>
                    <div class="info-value">
                        <span class="order-number-highlight">{{ $order->order_number }}</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Table Number:</div>
                    <div class="info-value">{{ $order->table->table_number }} (Capacity: {{ $order->table->capacity }}
                        persons)</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Served By:</div>
                    <div class="info-value">{{ $order->waiter->name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cashier:</div>
                    <div class="info-value">{{ $order->cashier->name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Date:</div>
                    <div class="info-value">{{ $order->opened_at->format('d F Y, H:i') }} WIB</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Closed Date:</div>
                    <div class="info-value">{{ $order->closed_at->format('d F Y, H:i') }} WIB</div>
                </div>
            </div>
        </div>


        <!-- Items Section -->
        <div class="items-section">
            <div class="section-title">Order Details</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Item Description</th>
                        <th style="width: 15%;" class="text-right">Unit Price</th>
                        <th style="width: 10%;" class="text-center">Qty</th>
                        <th style="width: 20%;" class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>
                                <div class="item-name">{{ $item->menu->name }}</div>
                                @if ($item->notes)
                                    <div class="item-notes">
                                        <strong>Note:</strong> {{ $item->notes }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right"><strong>Rp
                                    {{ number_format($item->subtotal, 0, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Payment Section -->
        <div class="payment-section">
            <div class="section-title">Payment Information</div>
            <div class="payment-methods">
                @if ($order->payments && $order->payments->count() > 0)
                    @foreach ($order->payments as $payment)
                        <div class="payment-item">
                            <div class="payment-method">
                                <strong>{{ strtoupper($payment->payment_method) }}</strong>
                            </div>
                            <div class="payment-amount">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </div>
                            <div class="payment-status">
                                <span class="badge badge-success">{{ ucfirst($payment->status) }}</span>
                            </div>
                        </div>
                        @if ($payment->reference_number)
                            <div style="padding-left: 120px; font-size: 10px; color: #6c757d; margin-top: 3px;">
                                Ref: {{ $payment->reference_number }}
                            </div>
                        @endif
                    @endforeach
                @else
                    <div style="text-align: center; padding: 10px; color: #6c757d; font-style: italic;">
                        No payment records available
                    </div>
                @endif
            </div>
        </div>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="totals-label">Subtotal</td>
                    <td class="totals-amount">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="totals-label">Tax (10%)</td>
                    <td class="totals-amount">Rp {{ number_format($order->tax, 0, ',', '.') }}</td>
                </tr>
                <tr class="grand-total-row">
                    <td class="totals-label">GRAND TOTAL</td>
                    <td class="totals-amount">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="totals-label">Payment Status</td>
                    <td class="totals-amount">
                        <span class="badge badge-success">{{ strtoupper($order->payment_status) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="thank-you">THANK YOU FOR YOUR VISIT!</div>
            <div class="footer-message">
                We hope you enjoyed your meal. Please visit us again soon!
            </div>
            <div class="barcode-placeholder">
                {{ $order->order_number }}
            </div>
            <div class="footer-info">
                This is a computer-generated receipt and does not require a signature.<br>
                For any inquiries, please contact us at info@restaurantdeluxe.com<br>
                Receipt generated on: {{ now()->format('d F Y, H:i:s') }} WIB
            </div>
        </div>
    </div>
</body>

</html>
