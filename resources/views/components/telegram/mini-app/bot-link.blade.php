@props([
    'href',
    'confirmTitle',
    'confirmMessage',
])

<a
    href="{{ $href }}"
    {{ $attributes }}
    x-data
    data-tg-bot-link
    data-href="{{ $href }}"
    role="button"
    @click.prevent="$store.botConfirm.ask({
        href: @js($href),
        title: @js($confirmTitle),
        message: @js($confirmMessage),
    })"
>
    {{ $slot }}
</a>
