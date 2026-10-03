@props([
    'copy',
    'activeNav' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */

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

<nav class="tg-tabbar" aria-label="{{ $copy->get('menu.nav') }}" data-tg-tabbar>
    <div class="tg-tabbar-inner">
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                class="tg-tabbar-link {{ $activeNav === $item['key'] ? 'tg-tabbar-link-active' : '' }}"
                data-tg-tab="{{ $item['key'] }}"
                @if ($activeNav === $item['key']) aria-current="page" @endif
            >
                <span class="tg-tabbar-icon" aria-hidden="true">
                    @if ($item['icon'] === 'courses')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                    @elseif ($item['icon'] === 'resources')
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    @endif
                </span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
