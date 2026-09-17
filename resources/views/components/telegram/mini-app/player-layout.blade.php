@props([
    'copy',
    'title' => null,
    'backUrl' => null,
    'resource' => null,
    'isBookmarked' => false,
    'activeNav' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
@endphp

<!DOCTYPE html>
<html lang="{{ $copy->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <x-telegram.mini-app.theme-script />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="tg-page antialiased">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col">
        <header class="tg-header sticky top-0 z-20 px-4 py-3">
            <div class="flex items-center gap-3">
                @if ($backUrl)
                    <a
                        href="{{ $backUrl }}"
                        class="inline-flex h-9 shrink-0 items-center justify-center px-1 text-sm font-medium tg-link"
                        aria-label="{{ $copy->get('hub.back') }}"
                    >
                        ‹
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium tg-hint">{{ config('app.name') }}</p>
                    @if ($title)
                        <h1 class="truncate text-lg font-semibold tracking-tight">{{ $title }}</h1>
                    @endif
                </div>
                @if ($resource)
                    <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="shrink-0">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-9 items-center justify-center rounded-lg px-2 text-xs font-semibold tg-link"
                        >
                            {{ $isBookmarked ? $copy->get('saved.unsave') : $copy->get('saved.save') }}
                        </button>
                    </form>
                @endif
                <x-telegram.mini-app.menu :copy="$copy" :active-nav="$activeNav" />
            </div>
        </header>

        <main class="flex-1 py-3">
            @if (session('status'))
                <div class="mx-4 mb-3 tg-status">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    <script>
        (function () {
            const backUrl = @js($backUrl);
            const webApp = window.Telegram?.WebApp;

            if (! webApp?.BackButton) {
                return;
            }

            webApp.BackButton.show();
            webApp.BackButton.onClick(function () {
                if (backUrl) {
                    window.location.href = backUrl;
                    return;
                }

                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }

                webApp.close();
            });
        })();
    </script>
</body>
</html>
