@props([
    'copy',
    'title' => null,
    'backUrl' => null,
    'resource' => null,
    'isBookmarked' => false,
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-surface-white text-text-primary antialiased">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col">
        <header class="sticky top-0 z-20 border-b border-border-light/60 bg-surface-white/95 px-4 py-4 backdrop-blur">
            <div class="flex items-start gap-3">
                @if ($backUrl)
                    <a
                        href="{{ $backUrl }}"
                        class="mt-0.5 inline-flex shrink-0 items-center justify-center rounded-xl border border-border-light px-2.5 py-1.5 text-sm font-semibold text-text-secondary"
                        aria-label="{{ $copy->get('hub.back') }}"
                    >
                        ←
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold tracking-tight">{{ config('app.name') }}</p>
                    @if ($title)
                        <h1 class="mt-1 text-xl font-extrabold tracking-tight">{{ $title }}</h1>
                    @endif
                </div>
                @if ($resource)
                    <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="mt-0.5 shrink-0">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl border border-border-light px-2.5 py-1.5 text-xs font-semibold {{ $isBookmarked ? 'bg-primary-50 text-primary-700' : 'text-text-secondary' }}"
                        >
                            {{ $isBookmarked ? $copy->get('saved.unsave') : $copy->get('saved.save') }}
                        </button>
                    </form>
                @endif
            </div>
        </header>

        <main class="flex-1 px-4 py-5">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    <script>
        window.Telegram?.WebApp?.ready();
        window.Telegram?.WebApp?.expand();

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
