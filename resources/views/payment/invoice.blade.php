<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payment->merchant_order_id }} — Invoice</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px;
            color: #333;
            background: #fff;
        }
        .top-bar {
            text-align: center; color: #666; font-size: 14px; margin-bottom: 30px;
        }
        .header {
            padding-bottom: 20px;
            border-bottom: 3px solid #0062FF;
            margin-bottom: 40px;
        }
        .clearfix::after { content: ""; display: table; clear: both; }
        
        .seller-left { float: left; width: 60%; }
        .seller-info h2 { font-size: 26px; color: #0062FF; margin-bottom: 10px; }
        .seller-info p { font-size: 13px; color: #444; line-height: 1.6; margin: 5px 0; }
        
        .invoice-right { float: right; width: 35%; text-align: right; }
        .invoice-right-title { font-size: 28px; font-weight: bold; color: #0062FF; margin-bottom: 10px; }
        .invoice-details { font-size: 13px; margin-top: 10px; width: 100%; }
        .invoice-details td { padding: 4px 0; line-height: 1.6; }
        .invoice-details td:first-child { color: #555; padding-right: 10px; text-align: right; }
        .invoice-details td:nth-child(2) { text-align: left; font-weight: bold; }

        .address-section { margin-bottom: 40px; }
        .address-box { float: left; width: 48%; }
        .box-heading {
            background-color: #0062FF; color: #fff; padding: 8px 15px;
            font-size: 14px; font-weight: 600; display: inline-block;
        }
        .box-content {
            border: 2px solid #0062FF; border-top: none; padding: 20px; font-size: 13px;
            min-height: 100px;
        }
        .box-content strong { font-size: 15px; display: block; margin-bottom: 5px; }

        .items-table {
            width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px;
        }
        .items-table th { background-color: #0062FF; color: #fff; padding: 12px 10px; text-align: left; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #ddd; }
        .items-table th.right, .items-table td.right { text-align: right; }
        .items-table th.center, .items-table td.center { text-align: center; }

        .subtotal-row td {
            background-color: #0062FF; color: #fff; font-weight: bold; font-size: 14px; padding: 15px 10px;
        }

        .bottom-section { margin-top: 40px; }
        .terms { float: left; width: 50%; font-size: 13px; }
        .terms h4 { color: #0062FF; font-size: 15px; margin-bottom: 15px; }
        .terms ol { padding-left: 20px; line-height: 1.7; color: #555; }

        .totals {
            float: right; width: 40%;
            border: 2px solid #0062FF; border-radius: 8px; padding: 20px;
            background-color: #f9fcff; font-size: 13px;
        }
        .totals-row { margin-bottom: 12px; }
        .totals-row .label { display: inline-block; width: 60%; color: #555; }
        .totals-row .val { display: inline-block; width: 35%; text-align: right; font-weight: 600; }
        
        .totals-row.total {
            font-size: 16px; font-weight: bold; color: #0062FF;
            border-top: 2px solid #0062FF; padding-top: 15px; margin-top: 15px;
        }
        .totals-row.total .label, .totals-row.total .val { color: #0062FF; }

        .watermark {
            position: absolute; top: 40%; left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px; font-weight: bold;
            color: rgba(16, 185, 129, 0.08); /* Faint green */
            z-index: -1;
        }
    </style>
</head>
<body>

@if($payment->status === 'COMPLETED')
    <div class="watermark">PAID</div>
@endif

<div class="header clearfix">
    <div class="seller-left">
        <div class="seller-info">
            <h2>{{ env('APP_NAME', 'PhonePe POC') }}</h2>
            <p>123 Startup Avenue, Tech District</p>
            <p>GSTIN : 29ABCDE1234F1Z5</p>
            <p>Email : support@example.com</p>
        </div>
    </div>

    <div class="invoice-right">
        <div class="invoice-right-title">TAX INVOICE</div>
        <table class="invoice-details">
            <tr>
                <td>Invoice No.</td>
                <td>: INV-{{ substr($payment->merchant_order_id, 4) }}</td>
            </tr>
            <tr>
                <td>Date</td>
                <td>: {{ $payment->created_at->format('d M Y') }}</td>
            </tr>
            <tr>
                <td>Status</td>
                <td style="color: {{ $payment->status === 'COMPLETED' ? '#10b981' : '#f59e0b' }}">
                    : {{ $payment->status }}
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="address-section clearfix">
    <div class="address-box">
        <div class="box-heading">BILL TO</div>
        <div class="box-content">
            <strong>{{ $payment->name }}</strong>
            <p>Phone: +91 {{ $payment->phone }}</p>
            <p>Email: {{ $payment->email }}</p>
        </div>
    </div>
</div>

<table class="items-table">
    <thead>
        <tr>
            <th>S.No</th>
            <th>Description</th>
            <th class="center">Qty</th>
            <th class="right">Rate</th>
            <th class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>Payment Link Service — Order {{ $payment->merchant_order_id }}</td>
            <td class="center">1</td>
            <td class="right">₹{{ number_format($payment->amount, 2) }}</td>
            <td class="right">₹{{ number_format($payment->amount, 2) }}</td>
        </tr>

        <tr class="subtotal-row">
            <td colspan="2"><strong>TOTAL</strong></td>
            <td class="center"><strong>1</strong></td>
            <td class="right"></td>
            <td class="right"><strong>₹{{ number_format($payment->amount, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

<div class="bottom-section clearfix">
    <div class="terms">
        <h4>TERMS AND CONDITIONS</h4>
        <ol>
            <li>This is a computer-generated invoice.</li>
            <li>For any queries, contact support@example.com.</li>
            <li>All disputes are subject to local jurisdiction.</li>
        </ol>
    </div>

    <div class="totals">
        <div class="totals-row">
            <span class="label">Taxable Amount</span>
            <span class="val">₹{{ number_format($payment->amount * 0.82, 2) }}</span>
        </div>
        <div class="totals-row">
            <span class="label">IGST (18%)</span>
            <span class="val">₹{{ number_format($payment->amount * 0.18, 2) }}</span>
        </div>
        <div class="totals-row total">
            <span class="label">Total Amount</span>
            <span class="val">₹{{ number_format($payment->amount, 2) }}</span>
        </div>
    </div>
</div>

</body>
</html>
