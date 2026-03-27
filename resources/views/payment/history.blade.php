<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History — Admin</title>
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
            --error: #ef4444;
            --warning: #f59e0b;
            --purple: #7c3aed;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 40px 24px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 32px;
        }
        h1 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--purple), #ec4899);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }
        .btn-back {
            padding: 10px 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px; font-weight: 600;
            transition: all 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.08); }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 20px; text-align: left; border-bottom: 1px solid var(--border); }
        th {
            background: rgba(0,0,0,0.2);
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--muted);
        }
        td { font-size: 14px; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .status {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }
        .status.completed { background: rgba(16,185,129,0.15); color: var(--success); }
        .status.failed { background: rgba(239,68,68,0.15); color: var(--error); }
        .status.pending { background: rgba(245,158,11,0.15); color: var(--warning); }
        
        .empty-state {
            padding: 60px 20px; text-align: center; color: var(--muted);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>
            <div class="logo-icon">📋</div>
            Transaction History
        </h1>
        <a href="{{ url('/payment/checkout') }}" class="btn-back">← Back to Checkout</a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Order ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td style="font-weight: 500;">{{ $payment->name }}</td>
                    <td style="color: var(--muted);">{{ $payment->email }}</td>
                    <td>{{ $payment->phone }}</td>
                    <td style="font-family: monospace; font-size: 13px; color: var(--purple-light);">
                        {{ $payment->merchant_order_id }}
                    </td>
                    <td style="font-weight: 600;">₹{{ number_format($payment->amount, 2) }}</td>
                    <td>
                        <span class="status {{ strtolower($payment->status) }}">
                            {{ $payment->status }}
                        </span>
                    </td>
                    <td style="color: var(--muted); font-size: 13px;">
                        {{ $payment->created_at->format('M d, Y') }}
                    </td>
                    <td>
                        @if($payment->status === 'COMPLETED')
                            <a href="{{ route('payment.invoice', $payment->merchant_order_id) }}" 
                               style="display:inline-block; padding:6px 12px; background:var(--purple); color:#fff; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600;">
                                Download PDF
                            </a>
                        @else
                            <span style="color:var(--muted); font-size:12px;">N/A</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div style="font-size: 32px; margin-bottom: 12px;">📭</div>
                            No transactions found yet.
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
