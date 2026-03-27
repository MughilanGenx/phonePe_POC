<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful — PhonePe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0f0f1a;
            --surface: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.1);
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --purple: #7c3aed;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% 0%, rgba(16,185,129,0.12) 0%, transparent 60%);
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 440px;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .check-wrap {
            width: 80px; height: 80px;
            background: rgba(16,185,129,0.15);
            border: 2px solid rgba(16,185,129,0.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
            animation: pop 0.5s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        @keyframes pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .check-wrap svg { width: 40px; height: 40px; stroke: var(--success); stroke-width: 2.5; fill: none; }

        h1 { font-size: 26px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: var(--muted); font-size: 15px; margin-bottom: 32px; line-height: 1.5; }

        .detail-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        .detail-row:last-of-type { border-bottom: none; }
        .detail-row .key { color: var(--muted); }
        .detail-row .val { font-weight: 600; }
        .detail-block {
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 6px 16px;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px;
            border-radius: 12px;
            border: none;
            font-size: 14px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            background: linear-gradient(135deg, var(--purple), #a855f7);
            color: #fff;
            box-shadow: 0 4px 20px rgba(124,58,237,0.4);
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 26px rgba(124,58,237,0.5); }
    </style>
</head>
<body>
<div class="card">
    <div class="check-wrap">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>

    <h1>Payment Successful!</h1>
    <p class="subtitle">Your payment was processed successfully. Thank you!</p>

    @if(isset($payment))
        <div class="detail-block">
            <div class="detail-row">
                <span class="key">Name</span>
                <span class="val">{{ $payment->name }}</span>
            </div>
            <div class="detail-row">
                <span class="key">Amount</span>
                <span class="val" style="color:#10b981">₹{{ number_format($payment->amount, 2) }}</span>
            </div>
            <div class="detail-row">
                <span class="key">Order ID</span>
                <span class="val" style="font-size:12px; font-family:monospace">{{ $payment->merchant_order_id }}</span>
            </div>
            @if($payment->transaction_id)
            <div class="detail-row">
                <span class="key">Transaction ID</span>
                <span class="val" style="font-size:12px; font-family:monospace">{{ $payment->transaction_id }}</span>
            </div>
            @endif
        </div>
        <!-- Auto-trigger PDF download -->
        <iframe src="{{ route('payment.invoice', $payment->merchant_order_id) }}" style="display:none;"></iframe>
        <div style="display:flex; justify-content:center; gap: 12px; margin-top:20px;">
            <a href="{{ url('/payment/checkout') }}" class="btn">← Back to Checkout</a>
            
            <a href="{{ route('payment.invoice', $payment->merchant_order_id) }}" class="btn" style="background: linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.3)); border: 1px solid var(--success); color: var(--success); box-shadow: none;">
                📥 Download PDF
            </a>
        </div>
    @else
        <p class="subtitle" style="margin-bottom:32px">Your transaction has been recorded.</p>
        <a href="{{ url('/payment/checkout') }}" class="btn">← Back to Checkout</a>
    @endif
</div>
</body>
</html>