<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhonePe — Generate Payment Link</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --purple: #7c3aed;
            --purple-light: #a855f7;
            --purple-dark: #5b21b6;
            --pink: #ec4899;
            --bg: #0f0f1a;
            --surface: rgba(255,255,255,0.05);
            --border: rgba(255,255,255,0.1);
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --error: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-image:
                radial-gradient(ellipse 80% 50% at 20% 10%, rgba(124,58,237,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 90%, rgba(236,72,153,0.12) 0%, transparent 55%);
        }

        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4), 0 0 0 1px rgba(124,58,237,0.2);
        }

        .logo-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--purple), var(--pink));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .logo-text { font-size: 20px; font-weight: 700; }
        .logo-text span { color: var(--purple-light); }

        h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 32px; }

        .field { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 500; color: var(--muted); margin-bottom: 8px; letter-spacing: 0.03em; text-transform: uppercase; }

        input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 13px 16px;
            font-size: 15px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        input::placeholder { color: rgba(255,255,255,0.2); }
        input:focus {
            border-color: var(--purple-light);
            box-shadow: 0 0 0 3px rgba(168,85,247,0.15);
        }
        input.error { border-color: var(--error); }
        .field-error { color: var(--error); font-size: 12px; margin-top: 6px; }

        .amount-wrap { position: relative; }
        .amount-prefix {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-weight: 600; font-size: 15px;
        }
        .amount-wrap input { padding-left: 32px; }

        .btn {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: #fff;
            box-shadow: 0 4px 20px rgba(124,58,237,0.4);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 26px rgba(124,58,237,0.5); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* Spinner */
        .spinner {
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Link Result Box */
        .result-box {
            margin-top: 28px;
            background: rgba(16,185,129,0.08);
            border: 1px solid rgba(16,185,129,0.25);
            border-radius: 16px;
            padding: 24px;
            display: none;
            animation: fadeUp 0.3s ease;
        }
        @keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        .result-label {
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--success); margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .result-label::before { content: '✓'; }

        .link-display {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            color: var(--purple-light);
            word-break: break-all;
            margin-bottom: 14px;
            font-family: monospace;
        }

        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn-sm {
            flex: 1;
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-copy {
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-copy:hover { background: rgba(255,255,255,0.14); }
        .btn-whatsapp {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: #fff;
        }
        .btn-whatsapp:hover { filter: brightness(1.1); }

        /* Customer summary */
        .customer-pill {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 14px;
            font-size: 13px;
        }
        .customer-pill .avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--purple), var(--pink));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; flex-shrink: 0;
        }
        .customer-pill .info { flex: 1; }
        .customer-pill .name { font-weight: 600; }
        .customer-pill .meta { color: var(--muted); font-size: 12px; margin-top: 1px; }
        .customer-pill .amount-badge {
            background: linear-gradient(135deg, var(--purple), var(--purple-light));
            color: #fff;
            border-radius: 8px;
            padding: 4px 10px;
            font-weight: 700;
            font-size: 13px;
        }

        /* Toast */
        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: rgba(30,30,50,0.95);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            transform: translateY(80px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 9999;
            max-width: 320px;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.success { border-color: rgba(16,185,129,0.4); }
        .toast.danger  { border-color: rgba(239,68,68,0.4); }
        .toast-icon { font-size: 18px; }

        /* Responsive Breakpoints */
        @media (max-width: 600px) {
            body { padding: 16px; align-items: flex-start; }
            .card { padding: 32px 24px; border-radius: 20px; margin-top: 60px; }
            h1 { font-size: 22px; }
            .logo-wrap { margin-bottom: 24px; }
            .header-actions { top: 16px !important; right: 16px !important; }
            .btn-sm { font-size: 12px; padding: 8px 12px; }
            .customer-pill { padding: 8px 12px; }
        }

        @media (max-width: 400px) {
            .actions { flex-direction: column; }
            .btn-sm { width: 100%; flex: none; }
            .logo-icon { width: 36px; height: 36px; font-size: 16px; }
            .logo-text { font-size: 18px; }
        }
    </style>
</head>
<body>

<!-- Header Actions -->
<div class="header-actions" style="position: absolute; top: 24px; right: 24px; z-index: 10;">
    <a href="{{ url('/payment/history') }}" class="btn-sm btn-copy" style="text-decoration:none; display:inline-flex;">
        📋 View History
    </a>
</div>

<div class="card">
    <div class="logo-wrap">
        <div class="logo-icon">💸</div>
        <div class="logo-text">Phone<span>Pe</span> Pay</div>
    </div>

    <h1>Send Payment Link</h1>
    <p class="subtitle">Enter customer details and share a PhonePe payment link instantly.</p>

    <form id="paymentForm" novalidate>
        <div class="field">
            <label>Full Name</label>
            <input type="text" id="name" name="name" placeholder="John Doe" autocomplete="name" required>
            <div class="field-error" id="err-name"></div>
        </div>
        <div class="field">
            <label>Email Address</label>
            <input type="email" id="email" name="email" placeholder="john@example.com" autocomplete="email" required>
            <div class="field-error" id="err-email"></div>
        </div>
        <div class="field">
            <label>Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="9876543210" maxlength="10" pattern="[0-9]{10}" required>
            <div class="field-error" id="err-phone"></div>
        </div>
        <div class="field">
            <label>Amount (₹)</label>
            <div class="amount-wrap">
                <span class="amount-prefix">₹</span>
                <input type="number" id="amount" name="amount" placeholder="500" min="1" step="0.01" required>
            </div>
            <div class="field-error" id="err-amount"></div>
        </div>

        <button type="submit" class="btn btn-primary" id="submitBtn">
            <div class="spinner" id="spinner"></div>
            <span id="btnText">⚡ Generate Payment Link</span>
        </button>
    </form>

    <!-- Result Box (shown after successful generation) -->
    <div class="result-box" id="resultBox">
        <div class="result-label">Payment Link Ready</div>

        <div class="customer-pill" id="customerPill">
            <div class="avatar" id="avatarInitial">J</div>
            <div class="info">
                <div class="name" id="custName">—</div>
                <div class="meta" id="custMeta">—</div>
            </div>
            <div class="amount-badge" id="custAmount">₹0</div>
        </div>

        <div class="link-display" id="linkDisplay">—</div>

        <div class="actions">
            <button class="btn-sm btn-copy" id="copyBtn" onclick="copyLink()">
                📋 Copy Link
            </button>
            <button class="btn-sm btn-whatsapp" id="waBtn" onclick="shareWhatsApp()">
                💬 WhatsApp
            </button>
        </div>
    </div>
</div>

<div class="toast" id="toast">
    <span class="toast-icon" id="toastIcon">✓</span>
    <span id="toastMsg">Copied!</span>
</div>

<script>
    let generatedLink = '';

    document.getElementById('paymentForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!validate()) return;

        const btn   = document.getElementById('submitBtn');
        const spin  = document.getElementById('spinner');
        const text  = document.getElementById('btnText');

        btn.disabled = true;
        spin.style.display = 'block';
        text.textContent = 'Generating…';

        const data = {
            name:   document.getElementById('name').value.trim(),
            email:  document.getElementById('email').value.trim(),
            phone:  document.getElementById('phone').value.trim(),
            amount: document.getElementById('amount').value,
        };

        try {
            const res  = await fetch('/api/generate-payment-link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data),
            });

            const json = await res.json();

            if (!res.ok || !json.success) {
                showToast('danger', '❌ ' + (json.message || 'Something went wrong'));
                return;
            }

            generatedLink = json.local_link;
            showResult(json);
            showToast('success', '✅ Link generated successfully!');

        } catch (err) {
            showToast('danger', '❌ Network error. Please try again.');
        } finally {
            btn.disabled = false;
            spin.style.display = 'none';
            text.textContent = '⚡ Generate Payment Link';
        }
    });

    function showResult(json) {
        document.getElementById('resultBox').style.display = 'block';
        document.getElementById('linkDisplay').textContent = json.local_link;
        document.getElementById('custName').textContent    = json.name;
        document.getElementById('custMeta').textContent    = json.email + ' · ' + json.phone;
        document.getElementById('custAmount').textContent  = '₹' + parseFloat(json.amount).toLocaleString('en-IN');
        document.getElementById('avatarInitial').textContent = json.name.charAt(0).toUpperCase();
    }

    function copyLink() {
        if (!generatedLink) return;
        navigator.clipboard.writeText(generatedLink).then(() => {
            showToast('success', '📋 Link copied to clipboard!');
            const btn = document.getElementById('copyBtn');
            btn.textContent = '✓ Copied!';
            setTimeout(() => btn.innerHTML = '📋 Copy Link', 2000);
        });
    }

    function shareWhatsApp() {
        if (!generatedLink) return;
        const msg  = encodeURIComponent('Please complete your payment here: ' + generatedLink);
        window.open('https://wa.me/?text=' + msg, '_blank');
    }

    function validate() {
        const fields = ['name', 'email', 'phone', 'amount'];
        let valid = true;
        fields.forEach(f => clearError(f));

        const name   = document.getElementById('name').value.trim();
        const email  = document.getElementById('email').value.trim();
        const phone  = document.getElementById('phone').value.trim();
        const amount = document.getElementById('amount').value;

        if (!name)                          { setError('name',   'Full name is required');           valid = false; }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))
                                            { setError('email',  'Valid email is required');          valid = false; }
        if (!phone || !/^\d{10}$/.test(phone))
                                            { setError('phone',  'Enter a valid 10-digit number');   valid = false; }
        if (!amount || parseFloat(amount) < 1)
                                            { setError('amount', 'Amount must be at least ₹1');      valid = false; }
        return valid;
    }

    function setError(id, msg) {
        document.getElementById(id).classList.add('error');
        document.getElementById('err-' + id).textContent = msg;
    }
    function clearError(id) {
        document.getElementById(id).classList.remove('error');
        document.getElementById('err-' + id).textContent = '';
    }

    // Clear errors on input
    ['name','email','phone','amount'].forEach(id => {
        document.getElementById(id).addEventListener('input', () => clearError(id));
    });

    function showToast(type, msg) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        toast.className = 'toast ' + type + ' show';
        setTimeout(() => toast.className = 'toast ' + type, 3500);
    }
</script>
</body>
</html>