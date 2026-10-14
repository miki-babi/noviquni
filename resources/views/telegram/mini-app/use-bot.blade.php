@php
    /** @var \App\Support\TelegramCopy $copy */
    $themeClass = (auth()->user()?->theme ?? 'dark') === 'light' ? 'light' : 'dark';
@endphp
<!DOCTYPE html>
<html lang="{{ $copy->locale }}" class="{{ $themeClass }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <x-telegram.mini-app.theme-script />
</head>
<body class="tg-page antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center gap-3 px-6 text-center">
        <p class="text-base font-semibold text-text-primary">{{ config('app.name') }}</p>
        <p class="text-sm text-text-secondary">{{ $copy->get('mini_app.use_bot') }}</p>
    </main>
    <script>
        (function () {
            const tg = window.Telegram?.WebApp;
            if (! tg) {
                return;
            }
            try { tg.ready(); } catch (e) {}
            try { tg.expand(); } catch (e) {}
            window.setTimeout(function () {
                try { tg.close(); } catch (e) {}
            }, 1200);
        })();
    </script>
</body>
</html>
