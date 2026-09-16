@props([
    'payload',
])

@php
    $partA = array_values(array_filter(
        is_array($payload['partA'] ?? null) ? $payload['partA'] : [],
        fn ($question): bool => is_array($question),
    ));
    $partB = array_values(array_filter(
        is_array($payload['partB'] ?? null) ? $payload['partB'] : [],
        fn ($question): bool => is_array($question),
    ));
@endphp

<div
    class="space-y-10"
    x-data="{
        partA: @js($partA),
        partB: @js($partB),
        answers: {},
        partBAnswers: {},
        submitted: false,
        score: 0,
        get partATotal() { return this.partA.length },
        get explanations() {
            return this.partA.map(q => q.explanation || q.rationale || '')
        },
        selectAnswer(index, optionIndex) {
            if (this.submitted) return
            this.answers[index] = optionIndex
        },
        optionClass(index, optionIndex) {
            const selected = this.answers[index] === optionIndex
            if (! this.submitted) {
                return selected
                    ? 'border-primary-200 bg-primary-50 ring-2 ring-primary-100'
                    : 'border-border-light bg-surface-white hover:border-primary-100'
            }
            const correct = this.partA[index]?.answerIndex === optionIndex
            if (correct) return 'border-primary-200 bg-primary-50'
            if (selected) return 'border-red-300 bg-red-50'
            return 'border-border-light bg-surface-white opacity-60'
        },
        canSubmit() {
            if (this.partATotal === 0 && this.partB.length === 0) return false
            return Object.keys(this.answers).length === this.partATotal
        },
        submit() {
            if (! this.canSubmit() || this.submitted) return
            this.score = this.partA.reduce((sum, question, index) => {
                return sum + (this.answers[index] === question.answerIndex ? 1 : 0)
            }, 0)
            this.submitted = true
        },
        reset() {
            this.answers = {}
            this.partBAnswers = {}
            this.submitted = false
            this.score = 0
        },
    }"
>
    @if ($partA !== [])
        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-2xl font-bold text-text-primary">Part A — Multiple choice</h2>
                <template x-if="submitted">
                    <x-ui.badge tone="filled">
                        Part A score: <span x-text="score"></span>/<span x-text="partATotal"></span>
                    </x-ui.badge>
                </template>
            </div>

            <div class="space-y-6">
                @foreach ($partA as $index => $question)
                    <x-ui.card>
                        <x-study.multiple-choice :question="$question" :index="$index" />
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($partB !== [])
        <section class="space-y-4">
            <h2 class="text-2xl font-bold text-text-primary">Part B — Short answer</h2>
            <div class="space-y-6">
                @foreach ($partB as $index => $question)
                    <x-ui.card>
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-text-muted">Question {{ $index + 1 }}</span>
                                @if (isset($question['points']))
                                    <x-ui.badge>{{ $question['points'] }} pts</x-ui.badge>
                                @endif
                            </div>
                            <p class="text-lg font-semibold text-text-primary">{{ $question['question'] ?? '' }}</p>
                            <textarea
                                class="min-h-28 w-full rounded-card border border-border-light bg-surface-white px-4 py-3 text-text-primary outline-none focus:border-primary-100 focus:ring-2 focus:ring-primary-50 disabled:bg-surface-gray"
                                placeholder="Write your answer…"
                                x-model="partBAnswers[{{ $index }}]"
                                :disabled="submitted"
                            ></textarea>

                            <template x-if="submitted">
                                <div class="space-y-3 rounded-card border border-border-light bg-surface-gray p-4 text-sm">
                                    @if (filled($question['modelAnswer'] ?? null))
                                        <div>
                                            <p class="font-semibold text-text-primary">Model answer</p>
                                            <p class="mt-1 text-text-secondary">{{ $question['modelAnswer'] }}</p>
                                        </div>
                                    @endif
                                    @if (filled($question['gradingRubric'] ?? null))
                                        <div>
                                            <p class="font-semibold text-text-primary">Grading rubric</p>
                                            <p class="mt-1 text-text-secondary">{{ is_string($question['gradingRubric']) ? $question['gradingRubric'] : json_encode($question['gradingRubric']) }}</p>
                                        </div>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endif

    @if ($partA === [] && $partB === [])
        <p class="text-text-secondary">This exam has no questions yet.</p>
    @else
        <div class="flex flex-wrap gap-3">
            <template x-if="! submitted">
                <button
                    type="button"
                    class="rounded-pill bg-primary-200 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-300 disabled:opacity-40"
                    @click="submit()"
                    :disabled="! canSubmit()"
                >
                    Submit exam
                </button>
            </template>
            <template x-if="submitted">
                <button
                    type="button"
                    class="rounded-pill border border-border-light px-5 py-2.5 text-sm font-semibold text-text-secondary hover:bg-surface-gray"
                    @click="reset()"
                >
                    Try again
                </button>
            </template>
        </div>
    @endif
</div>
