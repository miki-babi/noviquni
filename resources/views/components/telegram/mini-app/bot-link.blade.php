@props([
    'href',
])

<a
    href="{{ $href }}"
    {{ $attributes }}
    data-tg-bot-link
    onclick="return window.tgOpenBotLink(this.href);"
>
    {{ $slot }}
</a>
