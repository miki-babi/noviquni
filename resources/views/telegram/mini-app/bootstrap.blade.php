<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <x-telegram.mini-app.theme-script />
</head>
<body class="tg-page antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-base font-semibold">{{ config('app.name') }}</p>
        @if (session('error'))
            <p id="tg-bootstrap-status" class="text-sm" style="color: var(--tg-destructive);">{{ session('error') }}</p>
        @else
            <p id="tg-bootstrap-status" class="text-sm tg-hint">Opening your study space…</p>
        @endif
        <form id="tg-session-form" method="POST" action="{{ route('tg.session.store', [], false) }}" class="hidden">
            @csrf
            <input type="hidden" name="init_data" id="init_data" value="">
            <input type="hidden" name="redirect" value="{{ $intended }}">
        </form>
    </main>
    <script>
        (function () {
            const form = document.getElementById('tg-session-form');
            const input = document.getElementById('init_data');
            const status = document.getElementById('tg-bootstrap-status');
            const diagnoseUrl = @json(route('tg.session.diagnose', [], false));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const maxWaitMs = 5000;
            const pollMs = 50;
            const startedAt = Date.now();

            function diagnose(payload) {
                fetch(diagnoseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                    keepalive: true,
                    credentials: 'same-origin',
                }).catch(function () {});
            }

            function clientContext(extra) {
                const tg = window.Telegram?.WebApp;
                const initData = tg?.initData || '';

                return Object.assign({
                    has_telegram: Boolean(tg),
                    has_init_data: Boolean(initData),
                    init_data_length: initData.length,
                    tg_platform: tg?.platform || null,
                    tg_version: tg?.version || null,
                    path: window.location.pathname,
                    waited_ms: Date.now() - startedAt,
                }, extra || {});
            }

            function showFallback() {
                diagnose(clientContext({ event: 'init_data_missing' }));

                if (!status || status.dataset.error === '1') {
                    return;
                }

                status.className = 'text-sm text-text-secondary';
                status.textContent = 'Open the study app from the bot menu button, or tap Open Courses after choosing Courses in the bot chat.';
            }

            function submitInitData(initData) {
                diagnose(clientContext({ event: 'init_data_present' }));
                input.value = initData;
                form.submit();
            }

            function tryAuthenticate() {
                const tg = window.Telegram?.WebApp;

                if (tg) {
                    tg.ready();
                    tg.expand();
                }

                const initData = tg?.initData || '';

                if (initData) {
                    submitInitData(initData);

                    return;
                }

                if (Date.now() - startedAt >= maxWaitMs) {
                    showFallback();

                    return;
                }

                window.setTimeout(tryAuthenticate, pollMs);
            }

            @if (session('error'))
                if (status) {
                    status.dataset.error = '1';
                }
                diagnose(clientContext({
                    event: 'auth_error',
                    message: status?.textContent?.slice(0, 200) || 'auth_error',
                }));
            @endif

            tryAuthenticate();
        })();
    </script>
</body>
</html>
