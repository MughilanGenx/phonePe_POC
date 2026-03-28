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
            max-width: 1200px;
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

        /* Responsive */
        @media (max-width: 800px) {
            body { padding: 24px 16px; }
            .header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .header div { width: 100%; display: flex; flex-direction: column; gap: 10px; }
            .btn-back { width: 100%; text-align: center; }
            h1 { font-size: 20px; }
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
        <div style="display: flex; gap: 12px;">
            <a href="{{ route('payment.refresh') }}" class="btn-back" style="background: var(--purple); border-color: var(--purple); color: #fff;">
                🔄 Sync Statuses
            </a>
            <a href="{{ url('/payment/checkout') }}" class="btn-back">← Back to Checkout</a>
        </div>
    </div>

    @if(session('success'))
        <div style="background: rgba(16,185,129,0.1); border: 1px solid var(--success); color: var(--success); padding: 12px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: rgba(239,68,68,0.1); border: 1px solid var(--error); color: var(--error); padding: 12px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">
            {{ session('error') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 20px; padding: 20px 24px;">
        <div style="font-size: 13px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 12px;">
            Import from PhonePe (payment link / dashboard)
        </div>
        <p style="font-size: 13px; color: var(--muted); line-height: 1.5; margin-bottom: 14px;">
            Use the <strong style="color: var(--text);">Merchant Order ID</strong> you set when creating the payment (or the ID shown as merchant reference in the transaction detail). The value that starts with <strong style="color: var(--text);">OMO</strong> in the list is usually PhonePe’s <em>internal</em> order id — the status API expects your <strong style="color: var(--text);">merchant</strong> order id instead. Open the transaction in the PhonePe dashboard and copy the correct field, then import here or click <strong>Sync Statuses</strong> for existing rows.
        </p>
        <form action="{{ route('payment.import.phonepe') }}" method="post" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            @csrf
            <input type="text" name="merchant_order_id" value="{{ old('merchant_order_id') }}" required placeholder="Merchant order id (not always the OMO… reference)"
                style="flex: 1; min-width: 220px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(0,0,0,0.25); color: var(--text); font-size: 14px;">
            <button type="submit" style="padding: 10px 20px; border-radius: 10px; border: none; background: var(--purple); color: #fff; font-weight: 600; font-size: 14px; cursor: pointer;">
                Import / refresh
            </button>
        </form>
        <p style="font-size: 12px; color: var(--muted); margin-top: 12px; line-height: 1.5;">
            CLI: <code style="background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 6px;">php artisan phonepe:sync-order YOUR_ORDER_ID</code>
            &nbsp;·&nbsp; Webhook URL: <code style="background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 6px;">{{ url('/api/phonepe/webhook') }}</code>
        </p>
        <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border); font-size: 12px; color: var(--muted); line-height: 1.65;">
            <strong style="color: var(--text);">Automatic “all history” from PhonePe</strong><br>
            The Checkout API only exposes <em>one order at a time</em> by Merchant Order ID — there is no single OAuth call that downloads the whole dashboard table like an export. In practice you combine:
            <span style="display:block; margin-top:8px;">
                (1) <strong style="color: var(--text);">Webhooks</strong> — register the URL above in PhonePe Developer Settings so new payments upsert automatically;<br>
                (2) <strong style="color: var(--text);">CSV + import</strong> — export or copy Merchant Order IDs from the Business dashboard, save as <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">storage/app/phonepe_merchant_order_ids.csv</code>, then run
                <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">php artisan phonepe:import-csv</code>;<br>
                (3) <strong style="color: var(--text);">Refresh</strong> — <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">php artisan phonepe:sync-pending</code> or <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">phonepe:sync-all</code> after importing.<br>
                (4) Optional scheduler: set <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">PHONEPE_SCHEDULE_SYNC_PENDING=true</code> in <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">.env</code> and run the OS cron / <code style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px;">php artisan schedule:work</code> so pending rows poll PhonePe every 15 minutes.
            </span>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table>
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Source</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Merchant order</th>
                    <th>PhonePe ref</th>
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
                    <td style="font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.04em;">
                        {{ $payment->source ?? 'app' }}
                    </td>
                    <td style="color: var(--muted);">{{ $payment->email }}</td>
                    <td>{{ $payment->phone }}</td>
                    <td style="font-family: monospace; font-size: 13px; color: var(--purple-light);">
                        {{ $payment->merchant_order_id }}
                    </td>
                    <td style="font-family: monospace; font-size: 11px; color: var(--muted);">
                        {{ $payment->phonepe_order_id ?? '—' }}
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
                        <a href="{{ route('payment.invoice', $payment->merchant_order_id) }}"
                           style="display:inline-block; padding:6px 12px; background:var(--purple); color:#fff; border-radius:6px; text-decoration:none; font-size:12px; font-weight:600;">
                            {{ $payment->status === 'COMPLETED' ? 'Invoice PDF' : 'Receipt PDF' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
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
</div>
</body>
</html>
