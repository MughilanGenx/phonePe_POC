<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed — PhonePe</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0f0f1a;
            --surface: rgba(255,255,255,0.04);
            --border: rgba(255,255,255,0.1);
            --text: #f8fafc;
            --muted: #94a3b8;
            --error: #ef4444;
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
                radial-gradient(ellipse 80% 50% at 50% 0%, rgba(239,68,68,0.1) 0%, transparent 60%);
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%; max-width: 440px;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .x-wrap {
            width: 80px; height: 80px;
            background: rgba(239,68,68,0.12);
            border: 2px solid rgba(239,68,68,0.35);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 28px;
            animation: pop 0.5s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        @keyframes pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .x-wrap svg { width: 38px; height: 38px; stroke: var(--error); stroke-width: 2.5; fill: none; }

        h1 { font-size: 26px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { color: var(--muted); font-size: 15px; margin-bottom: 32px; line-height: 1.5; }

        .error-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
            color: #fca5a5;
            margin-bottom: 32px;
            text-align: left;
        }

        .btn-group { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 24px;
            border-radius: 12px;
            border: none;
            font-size: 14px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--purple), #a855f7);
            color: #fff;
            box-shadow: 0 4px 20px rgba(124,58,237,0.4);
        }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-ghost {
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>
<div class="card">
    <div class="x-wrap">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </div>

    <h1>Payment Failed</h1>
    <p class="subtitle">Something went wrong with your payment. Please try again.</p>

    @if(session('error') || request()->has('error'))
        <div class="error-box">
            ⚠️ {{ session('error') ?? request('error') }}
        </div>
    @endif

    <div class="btn-group">
        <a href="{{ url('/payment/checkout') }}" class="btn btn-primary">↩ Try Again</a>
        <a href="{{ url('/') }}" class="btn btn-ghost">Home</a>
    </div>
</div>
</body>
</html>