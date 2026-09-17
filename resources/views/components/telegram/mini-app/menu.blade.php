@props([
    'copy',
    'activeNav' => null,
    'user' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    /** @var \App\Models\User|null $user */

    $displayName = $user?->name ?: 'Student';
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $botUsername = config('services.telegram.bot_username');

    $groups = [
        'study' => [
            ['key' => 'courses', 'route' => 'tg.browse', 'label' => $copy->get('menu.courses')],
            ['key' => 'resources', 'route' => 'tg.library', 'label' => $copy->get('menu.browse')],
            ['key' => 'continue', 'route' => 'tg.continue', 'label' => $copy->get('menu.continue')],
        ],
        'account' => [
            ['key' => 'profile', 'route' => 'tg.profile', 'label' => $copy->get('menu.profile')],
            ['key' => 'premium', 'route' => 'tg.premium', 'label' => $copy->get('menu.premium')],
        ],
        'other' => [
            ['key' => 'notifications', 'route' => 'tg.profile', 'label' => $copy->get('menu.notifications')],
            ['key' => 'saved', 'route' => 'tg.saved', 'label' => $copy->get('menu.saved')],
        ],
    ];
@endphp

<div x-data="{ open: false }" class="contents">
    <button
        type="button"
        class="tg-menu-button"
        @click="open = true"
        :aria-expanded="open.toString()"
        aria-label="{{ $copy->get('menu.open') }}"
        data-tg-menu-button
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6" aria-hidden="true">
            <path d="M4 6.75A.75.75 0 0 1 4.75 6h14.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 6.75Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 12Zm0 5.25a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H4.75a.75.75 0 0 1-.75-.75Z" />
        </svg>
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
            <span class="tg-avatar" aria-hidden="true">{{ $initial }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold">{{ $displayName }}</p>
                <p class="truncate text-xs tg-hint">{{ $copy->get('menu.nav') }}</p>
            </div>
            <button type="button" class="tg-menu-close" @click="open = false" aria-label="{{ $copy->get('menu.close') }}">
                ✕
            </button>
        </div>

        @foreach ($groups as $groupKey => $items)
            <p class="tg-section-title">{{ $copy->get('menu.group_'.$groupKey) }}</p>
            <div class="tg-section" style="margin-inline: 0.75rem;">
                @foreach ($items as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        class="tg-cell {{ $activeNav === $item['key'] ? 'tg-cell-active' : '' }}"
                        @click="open = false"
                    >
                        <span class="tg-cell-body">
                            <span class="tg-cell-title">{{ $item['label'] }}</span>
                        </span>
                        <span class="tg-cell-chevron" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        <div class="tg-section" style="margin-inline: 0.75rem;">
            <a
                href="https://t.me/{{ $botUsername }}?start=refer"
                class="tg-cell"
                onclick="window.Telegram?.WebApp?.openTelegramLink(this.href); return false;"
                @click="open = false"
            >
                <span class="tg-cell-body">
                    <span class="tg-cell-title">{{ $copy->get('menu.refer') }}</span>
                </span>
                <span class="tg-cell-chevron" aria-hidden="true">›</span>
            </a>
        </div>
    </aside>
</div>
