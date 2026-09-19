@props([
    'payload',
    'title' => null,
    'resource' => null,
    'isBookmarked' => false,
    'copy' => null,
])

@php
    $cards = array_values(array_filter(
        is_array($payload['cards'] ?? null) ? $payload['cards'] : [],
        fn ($card): bool => is_array($card),
    ));

    $saveLabel = $copy?->get('saved.save') ?? 'Save';
    $savedLabel = $copy?->get('saved.unsave') ?? 'Saved';
@endphp

@if ($cards === [])
    <p class="px-4 text-text-secondary">This flashcard set has no cards yet.</p>
@else
    <div
        class="flex flex-col gap-6 px-4 pb-2"
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
            get progress() {
                if (this.total === 0) return 0
                return ((this.current + 1) / this.total) * 100
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
        {{-- Hint + progress --}}
        <div class="space-y-2 px-2">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <template x-if="card.hint">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-200 transition-opacity hover:opacity-80"
                            @click="showHint = ! showHint"
                        >
                            
                            <span x-text="showHint ? 'Hide hint' : 'Hint'"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-90': showHint }" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </template>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    
                    <p class="text-sm font-medium text-text-muted tabular-nums">
                        <span x-text="current + 1"></span> / <span x-text="total"></span>
                    </p>
                    <span class="h-4 w-px bg-border-light" aria-hidden="true"></span>

            @if ($resource)
                <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="contents">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 transition hover:text-text-secondary active:scale-[0.98]"
                    >
                        @if ($isBookmarked)
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-primary-200" aria-hidden="true">
                                <path fill-rule="evenodd" d="M6.32 2.577a49.255 49.255 0 0 1 11.36 0c1.497.174 2.57 1.46 2.57 2.93V21a.75.75 0 0 1-1.085.67L12 18.089l-7.165 3.583A.75.75 0 0 1 3.75 21V5.507c0-1.47 1.073-2.756 2.57-2.93Z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $savedLabel }}</span>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                            <span>{{ $saveLabel }}</span>
                        @endif
                    </button>
                </form>
            @endif
                </div>
                
            </div>

            <template x-if="card.hint">
                <p
                    class="text-sm text-text-secondary"
                    x-show="showHint"
                    x-cloak
                    x-text="card.hint"
                ></p>
            </template>
        </div>

        {{-- Flashcard --}}
        <button
            type="button"
            class="flashcard w-full text-left active:scale-[0.995] transition-transform"
            @click="flip()"
            :class="{ 'is-flipped': flipped }"
            aria-label="Flip flashcard"
        >
            <div class="flashcard-inner">
                <div class="flashcard-face flashcard-front">
                    <div class="flashcard-panel">
                        <div class="flex items-start justify-between gap-3">
                            <template x-if="card.category">
                                <span
                                    class="inline-flex rounded-pill bg-primary-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary-300"
                                    x-text="card.category"
                                ></span>
                            </template>
                            <template x-if="! card.category">
                                <span></span>
                            </template>
                            
                        </div>

                        <div class="flex flex-1 items-center justify-center py-6">
                            <p
                                class="max-w-[18ch] text-center text-[1.35rem] font-bold leading-snug tracking-tight text-text-primary sm:max-w-none sm:text-2xl"
                                x-text="card.front"
                            ></p>
                        </div>

                        <div class="border-t border-border-light pt-4">
                            <p class="flex items-center justify-center gap-2 text-sm text-text-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4 text-primary-200" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Tap to reveal answer</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flashcard-face flashcard-back">
                    <div class="flashcard-panel">
                        <div class="flex items-start justify-between gap-3">
                            <template x-if="card.category">
                                <span
                                    class="inline-flex rounded-pill bg-primary-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary-300"
                                    x-text="card.category"
                                ></span>
                            </template>
                            <template x-if="! card.category">
                                <span></span>
                            </template>
                            
                        </div>

                        <div class="flex flex-1 items-center justify-center py-6">
                            <p
                                class="max-w-prose text-center text-lg font-semibold leading-relaxed text-text-primary sm:text-xl"
                                x-text="card.back"
                            ></p>
                        </div>

                        <div class="border-t border-border-light pt-4">
                            <p class="flex items-center justify-center gap-2 text-sm text-text-secondary">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4 text-primary-200" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <span>Tap to see question</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </button>

        

        {{-- Main navigation --}}
        <div class="flex items-center gap-2 sm:gap-3">
            <button
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-pill bg-surface-gray px-3 py-3 text-sm font-semibold text-text-secondary transition active:scale-[0.98] disabled:opacity-40"
                @click="prev()"
                :disabled="current === 0"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                <span>Previous</span>
            </button>

           

            <button
                type="button"
                class="inline-flex flex-[1.25] items-center justify-center gap-2 rounded-pill bg-primary-200 px-4 py-3.5 text-sm font-semibold text-white shadow-soft transition hover:bg-primary-300 active:scale-[0.98]"
                @click="next()"
                :disabled="current === total - 1"
            >
                <span>Next</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>

        {{-- Secondary actions --}}
        <div class="flex items-center justify-center gap-4 pt-1 text-sm font-medium text-text-muted">
            <button
                type="button"
                class="inline-flex items-center gap-2 transition hover:text-text-secondary active:scale-[0.98]"
                @click="shuffle()"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5" />
                </svg>
                <span>Shuffle</span>
            </button>

            <span class="h-4 w-px bg-border-light" aria-hidden="true"></span>

            @if ($resource)
                <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="contents">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 transition hover:text-text-secondary active:scale-[0.98]"
                    >
                        @if ($isBookmarked)
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-primary-200" aria-hidden="true">
                                <path fill-rule="evenodd" d="M6.32 2.577a49.255 49.255 0 0 1 11.36 0c1.497.174 2.57 1.46 2.57 2.93V21a.75.75 0 0 1-1.085.67L12 18.089l-7.165 3.583A.75.75 0 0 1 3.75 21V5.507c0-1.47 1.073-2.756 2.57-2.93Z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $savedLabel }}</span>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                            <span>{{ $saveLabel }}</span>
                        @endif
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif
