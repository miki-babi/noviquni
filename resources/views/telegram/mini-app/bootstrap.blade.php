<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
</head>
<body class="min-h-screen bg-surface-white text-text-primary antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-base font-semibold">{{ config('app.name') }}</p>
        @if (session('error'))
            <p id="tg-bootstrap-status" class="text-sm text-red-600">{{ session('error') }}</p>
        @else
            <p id="tg-bootstrap-status" class="text-sm text-text-secondary">Opening your study space…</p>
        @endif
        <form id="tg-session-form" method="POST" action="{{ route('tg.session.store') }}" class="hidden">
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
            const maxWaitMs = 1500;
            const pollMs = 50;
            const startedAt = Date.now();

            function showFallback() {
                if (!status || status.dataset.error === '1') {
                    return;
                }

                status.className = 'text-sm text-text-secondary';
                status.textContent = 'Open this page from the Noviq Uni Telegram bot.';
            }

            function submitInitData(initData) {
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
            @endif

            tryAuthenticate();
        })();
    </script>
</body>
</html>
