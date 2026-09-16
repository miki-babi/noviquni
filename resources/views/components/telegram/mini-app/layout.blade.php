@props([
    'copy',
    'activeNav' => 'browse',
    'title' => null,
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
</head>
<body class="min-h-screen bg-surface-white text-text-primary antialiased">
    <div class="mx-auto flex min-h-screen max-w-lg flex-col pb-24">
        <header class="sticky top-0 z-20 border-b border-border-light/60 bg-surface-white/95 px-4 py-4 backdrop-blur">
            <p class="text-sm font-bold tracking-tight">{{ config('app.name') }}</p>
            @if ($title)
                <h1 class="mt-1 text-xl font-extrabold tracking-tight">{{ $title }}</h1>
            @endif
        </header>

        <main class="flex-1 px-4 py-5">
            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border-light/60 bg-surface-white/95 backdrop-blur">
            <div class="mx-auto grid max-w-lg grid-cols-4 gap-1 px-2 py-2 text-center text-[11px] font-medium">
                <a href="{{ route('tg.continue') }}" class="rounded-xl px-2 py-2 {{ $activeNav === 'continue' ? 'bg-primary-50 text-primary-700' : 'text-text-secondary' }}">
                    {{ $copy->get('keyboard.continue') }}
                </a>
                <a href="{{ route('tg.browse') }}" class="rounded-xl px-2 py-2 {{ $activeNav === 'browse' ? 'bg-primary-50 text-primary-700' : 'text-text-secondary' }}">
                    {{ $copy->get('keyboard.browse') }}
                </a>
                <a href="{{ route('tg.profile') }}" class="rounded-xl px-2 py-2 {{ $activeNav === 'profile' ? 'bg-primary-50 text-primary-700' : 'text-text-secondary' }}">
                    {{ $copy->get('keyboard.profile') }}
                </a>
                <a href="{{ route('tg.premium') }}" class="rounded-xl px-2 py-2 {{ $activeNav === 'premium' ? 'bg-primary-50 text-primary-700' : 'text-text-secondary' }}">
                    {{ $copy->get('keyboard.premium') }}
                </a>
            </div>
        </nav>
    </div>

    <script>
        window.Telegram?.WebApp?.ready();
        window.Telegram?.WebApp?.expand();
    </script>
</body>
</html>
