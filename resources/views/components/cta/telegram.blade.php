@php
    $isPrimary = ($variant ?? 'primary') === 'primary';
@endphp

@if ($isPrimary)
    <a
        href="{{ $href }}"
        class="inline-flex items-center gap-2 rounded-pill bg-primary-200 px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-primary-300"
        rel="noopener noreferrer"
        target="_blank"
    >
        <span>{{ $label }}</span>
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs text-white" aria-hidden="true">→</span>
    </a>
@else
    <a
        href="{{ $href }}"
        class="inline-flex items-center gap-2 text-sm font-semibold text-text-primary transition hover:text-primary-200"
        rel="noopener noreferrer"
        target="_blank"
    >
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary-50 text-primary-300" aria-hidden="true">▶</span>
        <span>{{ $label }}</span>
    </a>
@endif
