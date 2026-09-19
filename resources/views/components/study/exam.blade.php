@props([
    'payload',
])

@php
    $instructions = filled($payload['instructions'] ?? null) ? (string) $payload['instructions'] : null;
    $questions = array_values(array_filter(
        is_array($payload['questions'] ?? null) ? $payload['questions'] : [],
        fn ($question): bool => is_array($question)
            && filled($question['question'] ?? null)
            && is_array($question['options'] ?? null)
            && ($question['options'] ?? []) !== [],
    ));
@endphp

@if ($questions === [])
    <p class="text-text-secondary">This exam has no questions yet.</p>
@else
    <div
        class="space-y-6"
        x-data="{
            questions: @js($questions),
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
            },
            reset() {
                this.answers = {}
                this.submitted = false
                this.score = 0
            },
        }"
    >
        @if ($instructions)
            <p class="text-sm text-text-secondary">{{ $instructions }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-medium text-text-muted">
                <span x-text="Object.keys(answers).length"></span> / <span x-text="total"></span> answered
            </p>
            <template x-if="submitted">
                <x-ui.badge tone="filled">
                    Score: <span x-text="score"></span>/<span x-text="total"></span>
                </x-ui.badge>
            </template>
        </div>

        <div class="space-y-6">
            @foreach ($questions as $index => $question)
                <x-ui.card>
                    <x-study.multiple-choice :question="$question" :index="$index" />
                </x-ui.card>
            @endforeach
        </div>

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
    </div>
@endif
