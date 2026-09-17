@props([
    'copy',
    'activeNav' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */

    $items = [
        ['key' => 'courses', 'route' => 'tg.browse', 'label' => $copy->get('keyboard.courses')],
        ['key' => 'resources', 'route' => 'tg.library', 'label' => $copy->get('keyboard.resources')],
        ['key' => 'saved', 'route' => 'tg.saved', 'label' => $copy->get('keyboard.saved')],
        ['key' => 'profile', 'route' => 'tg.profile', 'label' => $copy->get('keyboard.profile')],
    ];
@endphp

<div x-data="{ open: false }" class="contents">
    <button
        type="button"
        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[var(--tg-link)]"
        @click="open = true"
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
        x-transition.opacity
        class="tg-menu-overlay"
        @click="open = false"
        data-tg-menu-overlay
    ></div>

    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full"
        x-transition:enter-end="translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0"
        x-transition:leave-end="translate-y-full"
        class="tg-menu-sheet"
        role="dialog"
        aria-label="{{ $copy->get('menu.open') }}"
        data-tg-menu-sheet
        @keydown.escape.window="open = false"
    >
        <div class="tg-menu-handle"></div>
        <p class="tg-section-title">{{ $copy->get('menu.nav') }}</p>
        <div class="tg-section">
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
    </div>
</div>
