@props([
    'copy',
    'activeNav' => 'courses',
    'title' => null,
    'backUrl' => null,
    'nextUrl' => null,
    'nextLabel' => null,
    'user' => null,
    'showGreeting' => false,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    /** @var \App\Models\User|null $resolvedUser */
    $resolvedUser = $user ?? auth()->user();
    $themeClass = ($resolvedUser?->theme ?? 'dark') === 'light' ? 'light' : 'dark';
    $showTabBar = blank($backUrl);
    $displayName = $resolvedUser?->name ?: 'Student';
    $firstName = $resolvedUser ? $copy->firstName($resolvedUser) : 'Student';
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $photoUrl = $resolvedUser?->telegram_photo_url;
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
    <x-telegram.mini-app.bot-confirm-script />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body x-data @class(['tg-page antialiased', 'tg-page--with-tabbar' => $showTabBar])>
    <div class="mx-auto flex min-h-screen max-w-lg flex-col">
        <header class="tg-header sticky top-0 z-20 px-4 py-3">
            @if ($showGreeting)
                <div
                    class="tg-greeting"
                    x-data="{
                        photoUrl: @js($photoUrl),
                        displayName: @js($displayName),
                        init() {
                            const tgUser = window.Telegram?.WebApp?.initDataUnsafe?.user;
                            if (! tgUser) {
                                return;
                            }
                            if (tgUser.photo_url) {
                                this.photoUrl = tgUser.photo_url;
                            }
                            const name = [tgUser.first_name, tgUser.last_name].filter(Boolean).join(' ').trim();
                            if (name) {
                                this.displayName = name;
                            }
                        },
                    }"
                >
                    <div class="tg-greeting-text">
                        <h1 class="tg-greeting-hello">
                            {{ $copy->get('browse.greeting', ['name' => $firstName]) }}
                        </h1>
                        <p class="tg-greeting-sub">{{ $copy->get('browse.welcome_back') }}</p>
                    </div>
                    <a
                        href="{{ route('tg.profile') }}"
                        class="tg-greeting-avatar"
                        aria-label="{{ $copy->get('menu.profile') }}"
                    >
                        <span class="tg-avatar" aria-hidden="true">
                            <img
                                x-show="photoUrl"
                                x-cloak
                                :src="photoUrl"
                                @if ($photoUrl) src="{{ $photoUrl }}" @endif
                                alt=""
                                class="tg-avatar-img"
                            >
                            <span x-show="! photoUrl" x-text="displayName.charAt(0).toUpperCase()">{{ $initial }}</span>
                        </span>
                    </a>
                </div>
            @else
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
                </div>
            @endif
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

    @if ($showTabBar)
        <x-telegram.mini-app.tab-bar :copy="$copy" :active-nav="$activeNav" />
    @endif

    <x-telegram.mini-app.bot-confirm-modal :copy="$copy" />

    <x-telegram.mini-app.back-button-script :back-url="$backUrl" />
</body>
</html>
