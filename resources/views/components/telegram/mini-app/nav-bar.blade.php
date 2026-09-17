@props([
    'copy',
    'backUrl' => null,
    'nextUrl' => null,
    'nextLabel' => null,
])

@php
    /** @var \App\Support\TelegramCopy $copy */
    $nextLabel ??= $copy->get('nav.next');
@endphp

@if ($backUrl || $nextUrl)
    <nav class="tg-nav-bar" data-tg-nav-bar aria-label="{{ $copy->get('nav.label') }}">
        <div class="tg-nav-bar-inner">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="tg-nav-btn tg-nav-btn-back">
                    ‹ {{ $copy->get('nav.back') }}
                </a>
            @else
                <span class="tg-nav-btn tg-nav-btn-spacer" aria-hidden="true"></span>
            @endif

            @if ($nextUrl)
                <a href="{{ $nextUrl }}" class="tg-nav-btn tg-nav-btn-next">
                    {{ $nextLabel }} ›
                </a>
            @else
                <span class="tg-nav-btn tg-nav-btn-spacer" aria-hidden="true"></span>
            @endif
        </div>
    </nav>
@endif
