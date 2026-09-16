@props([
    'tone' => 'default',
])

@php
    $classes = match ($tone) {
        'filled' => 'bg-primary-200 text-white',
        default => 'bg-primary-50 text-primary-300',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-pill px-3 py-1 text-xs font-semibold', $classes]) }}>
    {{ $slot }}
</span>
