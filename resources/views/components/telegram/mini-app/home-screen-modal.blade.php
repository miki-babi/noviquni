@props([
    'copy',
])

<div
    data-tg-home-screen-modal
    x-data
    x-cloak
    x-show="$store.homeScreenPrompt.open"
    x-transition.opacity.duration.150ms
    class="tg-modal-overlay"
    @click.self="$store.homeScreenPrompt.dismiss()"
    @keydown.escape.window="$store.homeScreenPrompt.dismiss()"
>
    <div
        class="tg-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tg-home-screen-title"
        @click.stop
    >
        <p id="tg-home-screen-title" class="tg-modal-title">{{ $copy->get('home_screen.title') }}</p>
        <p class="tg-modal-message">{{ $copy->get('home_screen.message') }}</p>
        <div class="tg-modal-actions">
            <button
                type="button"
                class="tg-btn tg-btn-secondary"
                data-tg-home-screen-dismiss
                @click="$store.homeScreenPrompt.dismiss()"
            >
                {{ $copy->get('home_screen.not_now') }}
            </button>
            <button
                type="button"
                class="tg-btn"
                data-tg-home-screen-add
                @click="$store.homeScreenPrompt.add()"
            >
                {{ $copy->get('home_screen.add') }}
            </button>
        </div>
    </div>
</div>
