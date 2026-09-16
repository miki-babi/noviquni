@props([
    'rotate' => -6,
])

<p {{ $attributes->class(['font-gelica text-body-sm text-marker-orange sm:text-subheading']) }} style="transform: rotate({{ $rotate }}deg); transform-origin: left center;">
    {{ $slot }}
</p>
