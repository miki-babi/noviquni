@props([
    'payload',
])

@php
    $questions = array_values(array_filter(
        is_array($payload['questions'] ?? null) ? $payload['questions'] : [],
        fn ($question): bool => is_array($question),
    ));
@endphp

@if ($questions === [])
    <p class="text-text-secondary">This quiz has no questions yet.</p>
@else
    <div
        class="space-y-6"
        x-data="{
            questions: @js($questions),
            current: 0,
            answers: {},
            submitted: false,
            score: 0,
            get total() { return this.questions.length },
            get explanations() {
                return this.questions.map(q => q.explanation || '')
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
                const correct = this.questions[index]?.answerIndex === optionIndex
                if (correct) return 'border-primary-200 bg-primary-50'
                if (selected) return 'border-red-300 bg-red-50'
                return 'border-border-light bg-surface-white opacity-60'
            },
            canSubmit() {
                return Object.keys(this.answers).length === this.total
            },
            submit() {
                if (! this.canSubmit() || this.submitted) return
                this.score = this.questions.reduce((sum, question, index) => {
                    return sum + (this.answers[index] === question.answerIndex ? 1 : 0)
                }, 0)
                this.submitted = true
                this.current = 0
            },
            reset() {
                this.answers = {}
                this.submitted = false
                this.score = 0
                this.current = 0
            },
            next() {
                if (this.current < this.total - 1) this.current++
            },
            prev() {
                if (this.current > 0) this.current--
            },
        }"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-medium text-text-muted">
                <span x-text="current + 1"></span> of <span x-text="total"></span>
            </p>
            <template x-if="submitted">
                <x-ui.badge tone="filled">
                    Score: <span x-text="score"></span>/<span x-text="total"></span>
                </x-ui.badge>
            </template>
        </div>

        <div class="h-2 overflow-hidden rounded-pill bg-surface-gray">
            <div
                class="h-full rounded-pill bg-primary-100 transition-all"
                :style="`width: ${((current + 1) / total) * 100}%`"
            ></div>
        </div>

        <x-ui.card>
            @foreach ($questions as $index => $question)
                <div x-show="current === {{ $index }}" x-cloak>
                    <x-study.multiple-choice :question="$question" :index="$index" />
                </div>
            @endforeach
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                class="rounded-pill border border-border-light px-4 py-2 text-sm font-semibold text-text-secondary hover:bg-surface-gray disabled:opacity-40"
                @click="prev()"
                :disabled="current === 0"
            >
                Previous
            </button>

            <div class="flex flex-wrap gap-2">
                <template x-if="! submitted && current === total - 1">
                    <button
                        type="button"
                        class="rounded-pill bg-primary-200 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-300 disabled:opacity-40"
                        @click="submit()"
                        :disabled="! canSubmit()"
                    >
                        Submit quiz
                    </button>
                </template>
                <template x-if="submitted">
                    <button
                        type="button"
                        class="rounded-pill border border-border-light px-4 py-2 text-sm font-semibold text-text-secondary hover:bg-surface-gray"
                        @click="reset()"
                    >
                        Try again
                    </button>
                </template>
                <button
                    type="button"
                    class="rounded-pill bg-primary-200 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-300 disabled:opacity-40"
                    @click="next()"
                    :disabled="current === total - 1"
                    x-show="! submitted || current < total - 1"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
@endif
