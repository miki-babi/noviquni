@props([
    'copy',
    'activeNav' => 'courses',
    'title' => null,
    'backUrl' => null,
    'nextUrl' => null,
    'nextLabel' => null,
    'user' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    /** @var \App\Models\User|null $resolvedUser */
    $resolvedUser = $user ?? auth()->user();
    $themeClass = ($resolvedUser?->theme ?? 'dark') === 'light' ? 'light' : 'dark';
@endphp

<!DOCTYPE html>
<html lang="{{ $copy->locale }}" class="{{ $themeClass }}">
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
            <div class="flex items-center gap-2">
                @if ($backUrl)
                    <a
                        href="{{ $backUrl }}"
                        class="tg-header-back"
                        aria-label="{{ $copy->get('nav.back') }}"
                        data-tg-header-back
                    >
                        ‹
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    @if ($title)
                        <h1 class="tg-page-title truncate">{{ $title }}</h1>
                    @endif
                </div>
                <x-telegram.mini-app.menu :copy="$copy" :active-nav="$activeNav" :user="$resolvedUser" />
            </div>
        </header>

        <main class="flex-1 py-3 {{ ($backUrl || $nextUrl) ? 'pb-2' : '' }}">
            @if (session('status'))
                <div class="mx-4 mb-3 tg-status">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <x-telegram.mini-app.nav-bar
            :copy="$copy"
            :back-url="$backUrl"
            :next-url="$nextUrl"
            :next-label="$nextLabel"
        />
    </div>

    <x-telegram.mini-app.back-button-script :back-url="$backUrl" />
</body>
</html>
