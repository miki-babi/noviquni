<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Premium payment</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7f5;
            --card: #ffffff;
            --text: #14231a;
            --muted: #5b6b62;
            --accent: #0f7a4a;
            --warn: #9a5b00;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif;
            background: radial-gradient(circle at top, #e7f5ee, var(--bg));
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }
        .card {
            width: min(28rem, 100%);
            background: var(--card);
            border-radius: 1.25rem;
            padding: 1.5rem;
            box-shadow: 0 12px 40px rgba(20, 35, 26, 0.08);
        }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { margin: 0 0 0.75rem; color: var(--muted); line-height: 1.5; }
        .status { font-weight: 700; color: var(--accent); }
        .status[data-state="pending"], .status[data-state="review_required"] { color: var(--warn); }
        .status[data-state="failed"], .status[data-state="expired"], .status[data-state="cancelled"], .status[data-state="rejected"] { color: #a11; }
        a.button {
            display: inline-block;
            margin-top: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 999px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Premium checkout</h1>
        <p id="message">Checking your payment…</p>
        <p class="status" id="status" data-state="{{ $payment->deposit_status ?? $payment->status->value }}">
            {{ $payment->deposit_status ?? $payment->status->value }}
        </p>
        <a class="button" id="cta" href="{{ $premiumUrl }}" hidden>Back to Premium</a>
    </div>

    <script>
        const statusUrl = @json($statusUrl);
        const premiumUrl = @json($premiumUrl);
        const messageEl = document.getElementById('message');
        const statusEl = document.getElementById('status');
        const ctaEl = document.getElementById('cta');
        let attempts = 0;
        const maxAttempts = 40;

        function render(data) {
            const depositStatus = data.deposit_status || data.status;
            statusEl.textContent = depositStatus;
            statusEl.dataset.state = depositStatus;

            if (data.fulfilled || data.status === 'verified' || depositStatus === 'succeeded') {
                messageEl.textContent = 'Payment verified. Premium is now active.';
                ctaEl.hidden = false;
                ctaEl.href = premiumUrl;
                ctaEl.textContent = 'Open Premium';
                return true;
            }

            if (['failed', 'expired', 'cancelled'].includes(depositStatus) || data.status === 'rejected') {
                messageEl.textContent = 'This checkout did not complete. You can start a new payment from Premium.';
                ctaEl.hidden = false;
                ctaEl.href = premiumUrl;
                ctaEl.textContent = 'Try again';
                return true;
            }

            if (depositStatus === 'review_required') {
                messageEl.textContent = 'Your payment is under review. We will activate Premium once it is cleared.';
                ctaEl.hidden = false;
                return true;
            }

            messageEl.textContent = 'Waiting for payment verification…';
            return false;
        }

        async function poll() {
            attempts += 1;
            try {
                const response = await fetch(statusUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    const data = await response.json();
                    if (render(data)) {
                        return;
                    }
                }
            } catch (e) {}

            if (attempts >= maxAttempts) {
                messageEl.textContent = 'Still waiting. You can close this page — Premium will unlock automatically when verification finishes.';
                ctaEl.hidden = false;
                return;
            }

            const delay = Math.min(30000, 2000 * Math.pow(1.35, Math.min(attempts, 8)));
            setTimeout(poll, delay);
        }

        render({
            status: @json($payment->status->value),
            deposit_status: @json($payment->deposit_status),
            fulfilled: @json($payment->fulfilled_at !== null),
            premium_active: @json($payment->user?->hasActivePremium() ?? false),
        });

        poll();
    </script>
</body>
</html>
