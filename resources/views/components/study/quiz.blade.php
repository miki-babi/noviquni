@props([
    'payload',
    'resultUrl' => null,
    'trackDuration' => false,
    'reportUrl' => null,
    'reportSource' => null,
    'learningResourceId' => null,
    'dailyChallengeId' => null,
])

@php
    use App\Support\StudyRichText;

    $questions = array_values(array_filter(
        is_array($payload['questions'] ?? null) ? $payload['questions'] : [],
        fn ($question): bool => is_array($question)
            && ! StudyRichText::isEmpty($question['question'] ?? null)
            && is_array($question['options'] ?? null)
            && ($question['options'] ?? []) !== [],
    ));

    $timeLimitMinutes = is_numeric($payload['timeLimitMinutes'] ?? null) && (int) $payload['timeLimitMinutes'] > 0
        ? (int) $payload['timeLimitMinutes']
        : null;
    $configuredTimeLimitSeconds = $timeLimitMinutes !== null ? $timeLimitMinutes * 60 : null;
    $defaultTimeLimitMinutes = max(5, (int) ceil(count($questions) * 1.5));
    $defaultTimeLimitSeconds = $defaultTimeLimitMinutes * 60;
    $timedLabelMinutes = $timeLimitMinutes ?? $defaultTimeLimitMinutes;

    $scoringQuestions = array_map(
        fn (array $question): array => [
            'answerIndex' => (int) ($question['answerIndex'] ?? 0),
        ],
        $questions,
    );
@endphp

@if ($questions === [])
    <p class="text-text-secondary">This quiz has no questions yet.</p>
@else
    <div
        class="space-y-6"
        data-assessment-player="quiz"
        x-data="{
            questions: @js($scoringQuestions),
            current: 0,
            answers: {},
            submitted: false,
            score: 0,
            started: false,
            timerEnabled: false,
            configuredTimeLimitSeconds: @js($configuredTimeLimitSeconds),
            defaultTimeLimitSeconds: @js($defaultTimeLimitSeconds),
            timeLimitSeconds: null,
            remainingSeconds: null,
            timerId: null,
            timedOut: false,
            resultUrl: @js($resultUrl),
            resultPosted: false,
            trackDuration: @js((bool) $trackDuration),
            startedAtMs: null,
            reportUrl: @js($reportUrl),
            reportSource: @js($reportSource),
            learningResourceId: @js($learningResourceId),
            dailyChallengeId: @js($dailyChallengeId),
            reportOpen: false,
            reportIndex: null,
            reportReason: 'typo',
            reportNote: '',
            reportBusy: false,
            reportError: null,
            reportedIndexes: {},
            get total() { return this.questions.length },
            get hasTimer() {
                return this.timerEnabled
                    && typeof this.timeLimitSeconds === 'number'
                    && this.timeLimitSeconds > 0
            },
            get formattedTime() {
                const total = Math.max(0, this.remainingSeconds ?? 0)
                const hours = Math.floor(total / 3600)
                const minutes = Math.floor((total % 3600) / 60)
                const seconds = total % 60
                const pad = (value) => String(value).padStart(2, '0')
                if (hours > 0) {
                    return `${hours}:${pad(minutes)}:${pad(seconds)}`
                }
                return `${pad(minutes)}:${pad(seconds)}`
            },
            get isUrgent() {
                return this.hasTimer && ! this.submitted && (this.remainingSeconds ?? 0) <= 60
            },
            init() {
                this.$watch('submitted', (value) => {
                    if (value) this.stopTimer()
                })
            },
            startTimed() {
                this.timeLimitSeconds = this.configuredTimeLimitSeconds ?? this.defaultTimeLimitSeconds
                this.remainingSeconds = this.timeLimitSeconds
                this.timerEnabled = true
                this.startedAtMs = Date.now()
                this.started = true
                this.$nextTick(() => this.startTimer())
            },
            startUntimed() {
                this.stopTimer()
                this.timerEnabled = false
                this.timeLimitSeconds = null
                this.remainingSeconds = null
                this.startedAtMs = Date.now()
                this.started = true
            },
            elapsedDurationMs() {
                if (! this.trackDuration || this.startedAtMs === null) {
                    return 0
                }

                return Math.max(0, Date.now() - this.startedAtMs)
            },
            startTimer() {
                this.stopTimer()
                if (! this.hasTimer || this.submitted || ! this.started) return
                this.timerId = setInterval(() => {
                    if (this.submitted) {
                        this.stopTimer()
                        return
                    }
                    if ((this.remainingSeconds ?? 0) <= 1) {
                        this.remainingSeconds = 0
                        this.stopTimer()
                        this.finish({ timedOut: true })
                        return
                    }
                    this.remainingSeconds -= 1
                }, 1000)
            },
            stopTimer() {
                if (this.timerId !== null) {
                    clearInterval(this.timerId)
                    this.timerId = null
                }
            },
            selectAnswer(index, optionIndex) {
                if (! this.started || this.submitted) return
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
            finish(options = {}) {
                if (this.submitted) return
                this.timedOut = Boolean(options.timedOut)
                this.score = this.questions.reduce((sum, question, index) => {
                    return sum + (this.answers[index] === question.answerIndex ? 1 : 0)
                }, 0)
                this.submitted = true
                this.current = 0
                this.stopTimer()
                this.postResult()
            },
            async postResult() {
                if (! this.resultUrl || this.resultPosted) return
                this.resultPosted = true

                try {
                    const response = await fetch(this.resultUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            answers: this.answers,
                            score: this.score,
                            total: this.total,
                            timed_out: this.timedOut,
                            duration_ms: this.elapsedDurationMs(),
                        }),
                    })

                    if (response.ok) {
                        const payload = await response.json()
                        if (typeof payload.score === 'number') this.score = payload.score
                    }
                } catch (error) {
                    // Keep quiz UX intact even if analytics logging fails.
                }
            },
            submit() {
                if (! this.canSubmit() || this.submitted) return
                this.finish()
            },
            reset() {
                this.stopTimer()
                this.answers = {}
                this.submitted = false
                this.score = 0
                this.current = 0
                this.timedOut = false
                this.resultPosted = false
                this.started = false
                this.timerEnabled = false
                this.timeLimitSeconds = null
                this.remainingSeconds = null
                this.startedAtMs = null
            },
            next() {
                if (this.current < this.total - 1) this.current++
            },
            prev() {
                if (this.current > 0) this.current--
            },
            isReported(index) {
                return Boolean(this.reportedIndexes[index])
            },
            openReport(index) {
                if (! this.reportUrl || this.isReported(index)) return
                this.reportIndex = index
                this.reportReason = 'typo'
                this.reportNote = ''
                this.reportError = null
                this.reportOpen = true
            },
            closeReport(force = false) {
                if (this.reportBusy && ! force) return
                this.reportOpen = false
                this.reportIndex = null
                this.reportError = null
            },
            canSubmitReport() {
                if (this.reportReason === 'other') {
                    return (this.reportNote || '').trim().length >= 3
                }
                return true
            },
            async submitReport() {
                if (! this.reportUrl || this.reportBusy || ! this.canSubmitReport() || this.reportIndex === null) return
                this.reportBusy = true
                this.reportError = null
                try {
                    const response = await fetch(this.reportUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            source: this.reportSource,
                            learning_resource_id: this.learningResourceId,
                            daily_challenge_id: this.dailyChallengeId,
                            question_index: this.reportIndex,
                            reason: this.reportReason,
                            note: (this.reportNote || '').trim() || null,
                        }),
                    })
                    const payload = await response.json().catch(() => ({}))
                    if (! response.ok) {
                        this.reportError = payload.message
                            || payload.errors?.note?.[0]
                            || 'Could not send report. Try again.'
                        return
                    }
                    this.reportedIndexes[this.reportIndex] = true
                    this.reportBusy = false
                    this.closeReport(true)
                } catch (error) {
                    this.reportError = 'Could not send report. Try again.'
                } finally {
                    this.reportBusy = false
                }
            },
        }"
    >
        <div
            data-assessment-start-modal
            x-show="! started"
            x-cloak
            class="tg-modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quiz-start-title"
        >
            <div class="tg-modal" @click.stop>
                <p id="quiz-start-title" class="tg-modal-title">Start this quiz</p>
                <p class="tg-modal-message">
                    Do you want a timed attempt? Timed mode uses a countdown and auto-submits when time runs out.
                </p>
                <div class="tg-modal-actions">
                    <button
                        type="button"
                        class="tg-btn tg-btn-secondary"
                        data-start-untimed
                        @click="startUntimed()"
                    >
                        No timer
                    </button>
                    <button
                        type="button"
                        class="tg-btn"
                        data-start-timed
                        @click="startTimed()"
                    >
                        Timed ({{ $timedLabelMinutes }} min)
                    </button>
                </div>
            </div>
        </div>

        <div x-show="started" x-cloak class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-medium text-text-muted">
                    <span x-text="current + 1"></span> of <span x-text="total"></span>
                </p>
                <div class="flex items-center gap-3">
                    <p
                        class="text-sm font-semibold tabular-nums"
                        data-exam-timer
                        x-show="hasTimer"
                        x-cloak
                        :class="isUrgent ? 'text-red-500' : 'text-text-muted'"
                        x-text="formattedTime"
                        role="timer"
                        aria-live="polite"
                        aria-label="Time remaining"
                    ></p>
                    <template x-if="submitted">
                        <x-ui.badge tone="filled">
                            Score: <span x-text="score"></span>/<span x-text="total"></span>
                        </x-ui.badge>
                    </template>
                </div>
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

        <x-study.question-report-modal />
    </div>
@endif
