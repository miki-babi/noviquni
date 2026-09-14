<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Telegram webhook status</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f1ea;
            --card: #fffdf8;
            --ink: #1c1917;
            --muted: #78716c;
            --line: #e7e5e4;
            --ok: #166534;
            --ok-bg: #dcfce7;
            --warn: #9a3412;
            --warn-bg: #ffedd5;
            --err: #991b1b;
            --err-bg: #fee2e2;
            --accent: #0f766e;
            --accent-ink: #ecfdf5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 40%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .wrap {
            max-width: 42rem;
            margin: 0 auto;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(28, 25, 23, 0.06);
        }
        h1 {
            margin: 0 0 0.35rem;
            font-size: 1.5rem;
        }
        .sub {
            margin: 0 0 1.25rem;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .flash {
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        .flash-success { background: var(--ok-bg); color: var(--ok); }
        .flash-error { background: var(--err-bg); color: var(--err); }
        .row {
            display: grid;
            gap: 0.35rem;
            padding: 0.9rem 0;
            border-top: 1px solid var(--line);
        }
        .row:first-of-type { border-top: 0; }
        .label {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .value {
            word-break: break-all;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.92rem;
        }
        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-ok { background: var(--ok-bg); color: var(--ok); }
        .badge-warn { background: var(--warn-bg); color: var(--warn); }
        .badge-err { background: var(--err-bg); color: var(--err); }
        .actions {
            margin-top: 1.25rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }
        button, .link {
            appearance: none;
            border: 0;
            border-radius: 0.7rem;
            padding: 0.75rem 1rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        button {
            background: var(--accent);
            color: var(--accent-ink);
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .link {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--line);
        }
        .note {
            margin-top: 1rem;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Telegram webhook</h1>
            <p class="sub">Check registration status and set the webhook to this app.</p>

            @if (session('success'))
                <div class="flash flash-success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="flash flash-error">{{ session('error') }}</div>
            @endif

            @if ($message)
                <div class="flash {{ $configured ? 'flash-error' : 'flash-error' }}">{{ $message }}</div>
            @endif

            <div class="row">
                <div class="label">Bot token</div>
                <div>
                    @if ($configured)
                        <span class="badge badge-ok">Configured</span>
                    @else
                        <span class="badge badge-err">Missing</span>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="label">Expected webhook URL</div>
                <div class="value">{{ $expected_webhook_url }}</div>
            </div>

            <div class="row">
                <div class="label">Registered URL</div>
                <div class="value">{{ filled($webhook['url'] ?? null) ? $webhook['url'] : 'Not set' }}</div>
            </div>

            <div class="row">
                <div class="label">Status</div>
                <div>
                    @if ($matches_expected_url)
                        <span class="badge badge-ok">Matched</span>
                    @elseif ($webhook_is_set)
                        <span class="badge badge-warn">Different URL</span>
                    @else
                        <span class="badge badge-err">Not set</span>
                    @endif
                </div>
            </div>

            @if (is_array($webhook))
                <div class="row">
                    <div class="label">Pending updates</div>
                    <div class="value">{{ $webhook['pending_update_count'] ?? 0 }}</div>
                </div>

                @if (! empty($webhook['last_error_message']))
                    <div class="row">
                        <div class="label">Last error</div>
                        <div class="value">{{ $webhook['last_error_message'] }}</div>
                    </div>
                    @if (! empty($webhook['last_error_date']))
                        <div class="row">
                            <div class="label">Last error at</div>
                            <div class="value">{{ \Illuminate\Support\Carbon::createFromTimestamp($webhook['last_error_date'])->toDateTimeString() }}</div>
                        </div>
                    @endif
                @endif
            @endif

            <div class="actions">
                @if ($can_set_webhook)
                    <form method="POST" action="{{ route('telegram.webhook-set') }}">
                        @csrf
                        <button type="submit">
                            {{ $webhook_is_set ? 'Update webhook to this app' : 'Set webhook' }}
                        </button>
                    </form>
                @endif

                <a class="link" href="{{ route('telegram.webhook-status') }}">Refresh</a>
                <a class="link" href="{{ url('/admin') }}">Admin</a>
            </div>

            <p class="note">
                Telegram requires a public HTTPS URL. Local `http://localhost` usually cannot receive webhooks unless you use a tunnel (e.g. ngrok) and set <code>APP_URL</code> to that HTTPS URL first.
            </p>
        </div>
    </div>
</body>
</html>
