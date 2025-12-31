<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo {
            max-height: 60px;
            max-width: 200px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .header-right {
            text-align: right;
        }
        .invoice-info {
            margin-bottom: 30px;
        }
        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-info td {
            padding: 5px 0;
        }
        .invoice-info td:first-child {
            font-weight: bold;
            width: 150px;
        }
        .billing-details {
            margin-bottom: 30px;
        }
        .billing-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .billing-details td {
            padding: 5px;
            vertical-align: top;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th,
        .items-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .items-table .text-right {
            text-align: right;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .total-row {
            padding: 5px 0;
        }
        .total-row.total {
            font-size: 16px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @php
                $logoPath = public_path('logo.png');
                if (!file_exists($logoPath)) {
                    $logoPath = public_path('M.B.E.S.T-logo.png');
                }
            @endphp
            @if(file_exists($logoPath))
                <img src="{{ $logoPath }}" alt="Logo" class="logo">
            @endif
            <div>
                <h1>INVOICE</h1>
                <div style="margin-top: 5px;">
                    <strong>Invoice #:</strong> {{ $invoice->invoice_number }}
                </div>
            </div>
        </div>
        <div class="header-right">
            <strong>{{ config('app.name', 'MBEST') }}</strong>
        </div>
    </div>

    <div class="invoice-info">
        <table>
            <tr>
                <td>Issue Date:</td>
                <td>{{ $invoice->issue_date->format('F d, Y') }}</td>
            </tr>
            <tr>
                <td>Due Date:</td>
                <td>{{ $invoice->due_date->format('F d, Y') }}</td>
            </tr>
            <tr>
                <td>Status:</td>
                <td>
                    <strong style="text-transform: uppercase; color: {{ $invoice->status === 'paid' ? 'green' : ($invoice->status === 'overdue' ? 'red' : 'orange') }};">
                        {{ $invoice->status }}
                    </strong>
                </td>
            </tr>
            @if($invoice->period_start && $invoice->period_end)
            <tr>
                <td>Billing Period:</td>
                <td>{{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="billing-details">
        <table>
            <tr>
                <td style="width: 50%;">
                    <strong>Bill To:</strong><br>
                    @if($invoice->parent)
                        {{ $invoice->parent->name }}<br>
                        {{ $invoice->parent->email }}
                    @else
                        N/A
                    @endif
                </td>
                <td style="width: 50%;">
                    @if($invoice->student && $invoice->student->user)
                        <strong>Student:</strong><br>
                        {{ $invoice->student->user->name }}<br>
                        Grade: {{ $invoice->student->grade ?? 'N/A' }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if($invoice->description)
    <div style="margin-bottom: 20px;">
        <strong>Description:</strong><br>
        {{ $invoice->description }}
    </div>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format($item->amount, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center; padding: 20px;">
                        No items
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <strong>Subtotal:</strong> {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}
        </div>
        <div class="total-row total">
            <strong>Total Amount:</strong> {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}
        </div>
    </div>

    @if($invoice->paid_date)
    <div style="margin-top: 20px; padding: 10px; background-color: #f0f9ff; border-left: 4px solid #0ea5e9;">
        <strong>Payment Information:</strong><br>
        Paid on: {{ $invoice->paid_date->format('F d, Y') }}<br>
        @if($invoice->payment_method)
            Payment Method: {{ $invoice->payment_method }}<br>
        @endif
        @if($invoice->transaction_id)
            Transaction ID: {{ $invoice->transaction_id }}
        @endif
    </div>
    @endif

    @if($invoice->notes)
    <div style="margin-top: 20px;">
        <strong>Notes:</strong><br>
        {{ $invoice->notes }}
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        @if($invoice->tutor && $invoice->tutor->user)
            <p>Tutor: {{ $invoice->tutor->user->name }}</p>
        @endif
    </div>
</body>
</html>

