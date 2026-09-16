@props([
    'eyebrow' => null,
    'align' => 'left',
])

<div {{ $attributes->class([
    'space-y-3',
    'text-center mx-auto max-w-2xl' => $align === 'center',
]) }}>
    @if ($eyebrow)
        <x-ui.badge>{{ $eyebrow }}</x-ui.badge>
    @endif
    <h2 class="text-[30px] font-bold leading-tight text-text-primary sm:text-[34px]">{{ $slot }}</h2>
    @isset($subtext)
        <p class="text-[15px] text-text-secondary {{ $align === 'center' ? 'mx-auto max-w-xl' : 'max-w-2xl' }}">{{ $subtext }}</p>
    @endisset
</div>
