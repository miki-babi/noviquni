@props([
    'payload',
])

@php
    $cards = array_values(array_filter(
        is_array($payload['cards'] ?? null) ? $payload['cards'] : [],
        fn ($card): bool => is_array($card),
    ));
@endphp

@if ($cards === [])
    <p class="text-text-secondary">This flashcard set has no cards yet.</p>
@else
    <div
        class="space-y-6"
        x-data="{
            cards: @js($cards),
            order: @js(array_keys($cards)),
            current: 0,
            flipped: false,
            showHint: false,
            get total() { return this.order.length },
            get card() {
                return this.cards[this.order[this.current]] || {}
            },
            flip() {
                this.flipped = ! this.flipped
            },
            next() {
                if (this.current < this.total - 1) {
                    this.current++
                    this.flipped = false
                    this.showHint = false
                }
            },
            prev() {
                if (this.current > 0) {
                    this.current--
                    this.flipped = false
                    this.showHint = false
                }
            },
            shuffle() {
                const next = [...this.order]
                for (let i = next.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1))
                    ;[next[i], next[j]] = [next[j], next[i]]
                }
                this.order = next
                this.current = 0
                this.flipped = false
                this.showHint = false
            },
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-medium text-text-muted">
                Card <span x-text="current + 1"></span> of <span x-text="total"></span>
            </p>
            <button
                type="button"
                class="rounded-pill border border-border-light px-4 py-2 text-sm font-semibold text-text-secondary hover:bg-surface-gray"
                @click="shuffle()"
            >
                Shuffle
            </button>
        </div>

        <button
            type="button"
            class="flashcard w-full text-left"
            @click="flip()"
            :class="{ 'is-flipped': flipped }"
            aria-label="Flip flashcard"
        >
            <div class="flashcard-inner">
                <div class="flashcard-face flashcard-front">
                    <x-ui.card class="flex min-h-56 flex-col justify-center !shadow-float">
                        <template x-if="card.category">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-text-muted" x-text="card.category"></p>
                        </template>
                        <p class="text-xl font-bold text-text-primary sm:text-2xl" x-text="card.front"></p>
                        <p class="mt-6 text-sm text-text-muted">Tap to reveal</p>
                    </x-ui.card>
                </div>
                <div class="flashcard-face flashcard-back">
                    <x-ui.card class="flex min-h-56 flex-col justify-center !bg-primary-50 !shadow-float">
                        <p class="text-lg font-semibold text-text-primary sm:text-xl" x-text="card.back"></p>
                    </x-ui.card>
                </div>
            </div>
        </button>

        <template x-if="card.hint">
            <div class="space-y-2">
                <button
                    type="button"
                    class="text-sm font-semibold text-primary-200 hover:underline"
                    @click="showHint = ! showHint"
                    x-text="showHint ? 'Hide hint' : 'Show hint'"
                ></button>
                <p class="text-sm text-text-secondary" x-show="showHint" x-text="card.hint" x-cloak></p>
            </div>
        </template>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                class="rounded-pill border border-border-light px-4 py-2 text-sm font-semibold text-text-secondary hover:bg-surface-gray disabled:opacity-40"
                @click="prev()"
                :disabled="current === 0"
            >
                Previous
            </button>
            <button
                type="button"
                class="rounded-pill bg-primary-200 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-300"
                @click="flip()"
            >
                Flip
            </button>
            <button
                type="button"
                class="rounded-pill border border-border-light px-4 py-2 text-sm font-semibold text-text-secondary hover:bg-surface-gray disabled:opacity-40"
                @click="next()"
                :disabled="current === total - 1"
            >
                Next
            </button>
        </div>
    </div>
@endif
