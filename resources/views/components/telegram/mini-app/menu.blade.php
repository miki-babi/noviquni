@props([
    'copy',
    'activeNav' => null,
    'user' => null,
    'icon' => 'hamburger',
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    /** @var \App\Models\User|null $user */

    $displayName = $user?->name ?: 'Student';
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $photoUrl = $user?->telegram_photo_url;
    $username = $user?->telegram_username;

    $items = [
        [
            'key' => 'courses',
            'route' => 'tg.browse',
            'label' => $copy->get('menu.courses'),
            'icon' => 'courses',
        ],
        [
            'key' => 'resources',
            'route' => 'tg.library',
            'label' => $copy->get('menu.resources'),
            'icon' => 'resources',
        ],
        [
            'key' => 'profile',
            'route' => 'tg.profile',
            'label' => $copy->get('menu.profile'),
            'icon' => 'profile',
        ],
    ];
@endphp

<div
    x-data="{
        open: false,
        photoUrl: @js($photoUrl),
        displayName: @js($displayName),
        username: @js($username),
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
            if (tgUser.username) {
                this.username = tgUser.username;
            }
        },
    }"
    class="contents"
>
    <button
        type="button"
        class="tg-menu-button"
        @click="open = true"
        :aria-expanded="open.toString()"
        aria-label="{{ $copy->get('menu.open') }}"
        data-tg-menu-button
    >
        @if ($icon === 'dots')
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                <path d="M12 6.75a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm0 6.5a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm0 6.5a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z" />
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                <path d="M4 6.75A.75.75 0 0 1 4.75 6h14.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 6.75Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 12Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H4.75a.75.75 0 0 1-.75-.75Z" />
            </svg>
        @endif
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.200ms
        class="tg-menu-overlay"
        @click="open = false"
        data-tg-menu-overlay
    ></div>

    <aside
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="tg-menu-drawer"
        role="dialog"
        aria-label="{{ $copy->get('menu.open') }}"
        data-tg-menu-sheet
        @keydown.escape.window="open = false"
    >
        <div class="tg-menu-drawer-header">
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
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold" x-text="displayName">{{ $displayName }}</p>
                <p class="truncate text-xs tg-hint" x-show="username" x-text="username ? '@' + username : ''">
                    @if ($username)
                        {{ '@'.$username }}
                    @endif
                </p>
            </div>
            <button type="button" class="tg-menu-close" @click="open = false" aria-label="{{ $copy->get('menu.close') }}">
                ✕
            </button>
        </div>

        <nav class="tg-drawer-nav" aria-label="{{ $copy->get('menu.nav') }}">
            @foreach ($items as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="tg-drawer-link {{ $activeNav === $item['key'] ? 'tg-drawer-link-active' : '' }}"
                    @click="open = false"
                >
                    <span class="tg-drawer-icon" aria-hidden="true">
                        @if ($item['icon'] === 'courses')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M11.7 2.805a.75.75 0 0 1 .6 0A60.65 60.65 0 0 1 22.83 8.8a.75.75 0 0 1-.231 1.337 49.95 49.95 0 0 0-9.902 3.912l-.003.002c-.114.06-.227.119-.34.18a.75.75 0 0 1-.707 0A50.85 50.85 0 0 0 7.5 12.173v-.224c0-.398.193-.786.52-1.022.4-.29.842-.54 1.319-.75a.75.75 0 0 0-.447-1.41 49.2 49.2 0 0 0-1.744.748 2.55 2.55 0 0 0-1.398 2.434V14.25a.75.75 0 0 0 .4.66l3.15 1.575a.75.75 0 0 0 .65 0l3.15-1.575a.75.75 0 0 0 .4-.66v-.224c.97.24 1.922.54 2.854.9a.75.75 0 0 0 .56-1.392A49.4 49.4 0 0 0 15 12.75v-.224a2.55 2.55 0 0 0-1.398-2.434 49.2 49.2 0 0 0-1.744-.748.75.75 0 1 0-.447 1.41c.477.21.919.46 1.319.75.327.236.52.624.52 1.022v.224a49.3 49.3 0 0 0-4.707-1.91A60.73 60.73 0 0 1 11.7 2.805Z" />
                                <path d="M13.06 15.473a48.45 48.45 0 0 1 4.242 1.508 1.5 1.5 0 0 1 1.198 1.468v2.176a.75.75 0 0 1-1.06.67 48.8 48.8 0 0 0-8.88 0 .75.75 0 0 1-1.06-.67v-2.176a1.5 1.5 0 0 1 1.198-1.468 48.45 48.45 0 0 1 4.242-1.508.75.75 0 0 1 .42 0Z" />
                            </svg>
                        @elseif ($item['icon'] === 'resources')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path d="M5.625 3.75a2.625 2.625 0 0 0-2.625 2.625v11.25c0 1.45 1.175 2.625 2.625 2.625h12.75a2.625 2.625 0 0 0 2.625-2.625V9.915a2.625 2.625 0 0 0-.77-1.857L15.566 3.77A2.625 2.625 0 0 0 13.71 3.75H5.625Z" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.68 18.68 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </span>
                    <span class="tg-drawer-link-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>
</div>
