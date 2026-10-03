@props([
    'copy',
])

<div
    data-tg-bot-confirm
    x-cloak
    x-show="$store.botConfirm.open"
    x-transition.opacity.duration.150ms
    class="tg-modal-overlay"
    @click.self="$store.botConfirm.cancel()"
    @keydown.escape.window="$store.botConfirm.cancel()"
>
    <div
        class="tg-modal"
        role="dialog"
        aria-modal="true"
        :aria-label="$store.botConfirm.title"
        @click.stop
    >
        <p class="tg-modal-title" x-text="$store.botConfirm.title"></p>
        <p class="tg-modal-message" x-text="$store.botConfirm.message"></p>
        <div class="tg-modal-actions">
            <button
                type="button"
                class="tg-btn tg-btn-secondary"
                @click="$store.botConfirm.cancel()"
            >
                {{ $copy->get('bot_confirm.cancel') }}
            </button>
            <button
                type="button"
                class="tg-btn"
                data-tg-bot-confirm-open
                @click="$store.botConfirm.confirm()"
            >
                {{ $copy->get('bot_confirm.open') }}
            </button>
        </div>
    </div>
</div>
