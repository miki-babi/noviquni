@props([
    'copy',
    'title' => null,
    'backUrl' => null,
    'nextUrl' => null,
    'nextLabel' => null,
    'resource' => null,
    'isBookmarked' => false,
    'showBookmark' => true,
    'showBotFooter' => false,
    'showMenu' => true,
    'menuIcon' => 'hamburger',
    'activeNav' => null,
    'user' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    /** @var \App\Models\User|null $resolvedUser */
    $resolvedUser = $user ?? auth()->user();
    $themeClass = ($resolvedUser?->theme ?? 'dark') === 'light' ? 'light' : 'dark';
    $botUsername = config('services.telegram.bot_username', 'noviquni_bot');
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
        <header @class([
            'tg-header sticky top-0 z-20 px-4',
            '!border-b-0 py-4' => $showBotFooter,
            'py-3' => ! $showBotFooter,
        ])>
            <div class="flex min-h-10 items-center gap-2">
                @if ($backUrl)
                    <a
                        href="{{ $backUrl }}"
                        class="tg-header-back"
                        aria-label="{{ $copy->get('nav.back') }}"
                        data-tg-header-back
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    @if ($title)
                        <h1 @class(['truncate', $showBotFooter ? 'text-base font-bold tracking-tight text-text-primary' : 'tg-page-title'])>
                            {{ $title }}
                        </h1>
                    @endif
                </div>
                @if ($showBookmark && $resource)
                    <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="shrink-0">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-xl px-2 text-xs font-semibold tg-link"
                        >
                            {{ $isBookmarked ? $copy->get('saved.unsave') : $copy->get('saved.save') }}
                        </button>
                    </form>
                @endif
                @if ($showMenu)
                    <x-telegram.mini-app.menu
                        :copy="$copy"
                        :active-nav="$activeNav"
                        :user="$resolvedUser"
                        :icon="$menuIcon"
                    />
                @endif
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

        @if ($showBotFooter)
            <footer class="px-4 py-5 text-center text-xs font-medium text-text-muted">
                {{ '@'.$botUsername }}
            </footer>
        @endif
    </div>

    <x-telegram.mini-app.back-button-script :back-url="$backUrl" />
</body>
</html>
