@props([
    'name',
    'class' => 'tg-lottie',
    'loop' => true,
    'autoplay' => true,
])

@php
    $src = \App\Support\TelegramLottie::url($name);
@endphp

@if ($src)
    <div
        {{ $attributes->class($class) }}
        data-lottie-src="{{ $src }}"
        data-lottie-loop="{{ $loop ? '1' : '0' }}"
        data-lottie-autoplay="{{ $autoplay ? '1' : '0' }}"
        role="img"
        aria-hidden="true"
    ></div>
@endif
