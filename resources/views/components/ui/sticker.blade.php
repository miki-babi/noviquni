@props([
    'type' => 'bolt',
    'rotate' => -8,
    'size' => 56,
])

@php
    $colors = match ($type) {
        'bear' => ['fill' => '#ff66cf', 'accent' => '#2b1a07'],
        'heart' => ['fill' => '#ff6f1e', 'accent' => '#171717'],
        'ghost' => ['fill' => '#fdfbf9', 'accent' => '#171717'],
        'sprout' => ['fill' => '#22c55e', 'accent' => '#171717'],
        default => ['fill' => '#3b82f6', 'accent' => '#171717'],
    };
@endphp

<span
    {{ $attributes->class(['pointer-events-none absolute inline-block animate-sticker-float']) }}
    style="--sticker-rotate: {{ $rotate }}deg; width: {{ $size }}px; height: {{ $size }}px;"
    aria-hidden="true"
>
    @if ($type === 'bolt')
        <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" fill="none">
            <path d="M28 6 14 34h14L20 58l30-36H36L48 6H28Z" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2" stroke-linejoin="round"/>
        </svg>
    @elseif ($type === 'bear')
        <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" fill="none">
            <circle cx="16" cy="18" r="10" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2"/>
            <circle cx="48" cy="18" r="10" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2"/>
            <circle cx="32" cy="34" r="20" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2"/>
            <circle cx="24" cy="32" r="2.5" fill="{{ $colors['accent'] }}"/>
            <circle cx="40" cy="32" r="2.5" fill="{{ $colors['accent'] }}"/>
            <ellipse cx="32" cy="40" rx="4" ry="3" fill="{{ $colors['accent'] }}"/>
        </svg>
    @elseif ($type === 'heart')
        <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" fill="none">
            <path d="M32 54S10 40 10 24a12 12 0 0 1 22-6 12 12 0 0 1 22 6c0 16-22 30-22 30Z" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2" stroke-linejoin="round"/>
            <circle cx="24" cy="26" r="2" fill="{{ $colors['accent'] }}"/>
            <circle cx="40" cy="26" r="2" fill="{{ $colors['accent'] }}"/>
            <path d="M26 34c2 2 6 2 8 0" stroke="{{ $colors['accent'] }}" stroke-width="2" stroke-linecap="round"/>
        </svg>
    @elseif ($type === 'ghost')
        <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" fill="none">
            <path d="M12 28c0-12 9-22 20-22s20 10 20 22v24l-7-5-7 5-6-5-7 5-7-5-6 5V28Z" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2" stroke-linejoin="round"/>
            <circle cx="24" cy="28" r="3" fill="{{ $colors['accent'] }}"/>
            <circle cx="40" cy="28" r="3" fill="{{ $colors['accent'] }}"/>
        </svg>
    @else
        <svg viewBox="0 0 64 64" width="{{ $size }}" height="{{ $size }}" fill="none">
            <circle cx="32" cy="34" r="16" fill="{{ $colors['fill'] }}" stroke="{{ $colors['accent'] }}" stroke-width="2"/>
            <path d="M32 10v8M24 14l4 6M40 14l-4 6" stroke="{{ $colors['accent'] }}" stroke-width="2" stroke-linecap="round"/>
        </svg>
    @endif
</span>
