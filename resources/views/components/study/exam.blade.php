@props([
    'payload',
    'resource' => null,
    'isBookmarked' => false,
    'copy' => null,
    'resultUrl' => null,
    'reportUrl' => null,
    'reportSource' => 'exam',
    'learningResourceId' => null,
])

@php
    use App\Support\StudyRichText;

    $title = filled($payload['title'] ?? null) ? (string) $payload['title'] : null;
    $instructions = filled($payload['instructions'] ?? null) ? (string) $payload['instructions'] : null;
    $timeLimitMinutes = is_numeric($payload['timeLimitMinutes'] ?? null) && (int) $payload['timeLimitMinutes'] > 0
        ? (int) $payload['timeLimitMinutes']
        : null;
    $questions = array_values(array_filter(
        is_array($payload['questions'] ?? null) ? $payload['questions'] : [],
        fn ($question): bool => is_array($question)
            && ! StudyRichText::isEmpty($question['question'] ?? null)
            && is_array($question['options'] ?? null)
            && ($question['options'] ?? []) !== [],
    ));
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

    $saveLabel = $copy?->get('saved.save') ?? 'Save';
    $savedLabel = $copy?->get('saved.unsave') ?? 'Saved';
@endphp

@if ($questions === [])
    <p class="px-4 text-text-secondary">This exam has no questions yet.</p>
@else
    <div
        class="flex flex-col gap-6 px-4 pb-2"
        data-assessment-player="exam"
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
            resultUrl: @js($resultUrl),
            timedOut: false,
            resultPosted: false,
            reportUrl: @js($reportUrl),
            reportSource: @js($reportSource),
            learningResourceId: @js($learningResourceId ?? $resource?->id),
            dailyChallengeId: null,
            reportOpen: false,
            reportIndex: null,
            reportReason: 'typo',
            reportNote: '',
            reportBusy: false,
            reportError: null,
            reportedIndexes: {},
            get total() { return this.questions.length },
            get progress() {
                if (this.total === 0) return 0
                return ((this.current + 1) / this.total) * 100
            },
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
                this.started = true
                this.$nextTick(() => this.startTimer())
            },
            startUntimed() {
                this.stopTimer()
                this.timerEnabled = false
                this.timeLimitSeconds = null
                this.remainingSeconds = null
                this.started = true
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
                            timed_out: this.timedOut,
                        }),
                    })

                    if (response.ok) {
                        const payload = await response.json()
                        if (typeof payload.score === 'number') this.score = payload.score
                    }
                } catch (error) {
                    // Keep exam UX intact even if analytics logging fails.
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
            aria-labelledby="exam-start-title"
        >
            <div class="tg-modal" @click.stop>
                <p id="exam-start-title" class="tg-modal-title">Start this exam</p>
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

        <div x-show="started" x-cloak class="flex flex-col gap-6">
            {{-- Meta + Save --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <p class="shrink-0 text-sm font-medium text-text-muted tabular-nums">
                            <span x-text="current + 1"></span> / <span x-text="total"></span>
                        </p>
                        <template x-if="submitted">
                            <x-ui.badge tone="filled">
                                Score: <span x-text="score"></span>/<span x-text="total"></span>
                            </x-ui.badge>
                        </template>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
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

                        @if ($resource)
                            <form method="POST" action="{{ route('tg.saved.toggle', $resource) }}" class="shrink-0">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-text-muted transition hover:text-text-secondary active:scale-[0.98]"
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

                <div
                    class="h-1.5 overflow-hidden rounded-pill bg-surface-gray"
                    role="progressbar"
                    :aria-valuenow="current + 1"
                    :aria-valuemax="total"
                >
                    <div
                        class="h-full rounded-pill bg-primary-200 transition-all duration-300 ease-out"
                        :style="`width: ${progress}%`"
                    ></div>
                </div>
            </div>

            @if ($title || $instructions)
                <div class="space-y-1.5">
                    @if ($title)
                        <h2 class="text-[15px] font-semibold text-text-primary">{{ $title }}</h2>
                    @endif
                    @if ($instructions)
                        <p class="text-sm leading-relaxed text-text-secondary">{{ $instructions }}</p>
                    @endif
                </div>
            @endif

            {{-- Question card --}}
            @foreach ($questions as $index => $question)
                @php
                    $options = array_values(is_array($question['options'] ?? null) ? $question['options'] : []);
                    $difficulty = $question['difficulty'] ?? null;
                    $questionHtml = StudyRichText::toHtml($question['question'] ?? null);
                    $explanationHtml = StudyRichText::toHtml($question['explanation'] ?? null);
                @endphp
                <div
                    class="flashcard-panel !min-h-0 space-y-5"
                    x-show="current === {{ $index }}"
                    x-cloak
                >
                    <div class="flex items-start justify-between gap-3">
                        @if (filled($difficulty))
                            <span class="inline-flex rounded-pill bg-primary-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-primary-300">
                                {{ ucfirst((string) $difficulty) }}
                            </span>
                        @else
                            <span class="inline-flex rounded-pill bg-surface-gray px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-text-muted">
                                Question {{ $index + 1 }}
                            </span>
                        @endif

                        <div x-show="reportUrl" x-cloak class="shrink-0">
                            <button
                                type="button"
                                class="text-xs font-semibold text-text-muted underline-offset-2 hover:text-text-secondary hover:underline"
                                x-show="! isReported({{ $index }})"
                                @click="openReport({{ $index }})"
                            >
                                Report
                            </button>
                            <span
                                class="text-xs font-semibold text-primary-200"
                                x-show="isReported({{ $index }})"
                                x-cloak
                            >
                                Reported
                            </span>
                        </div>
                    </div>

                    <div class="study-rich-text text-[1.2rem] font-bold leading-snug tracking-tight text-text-primary sm:text-xl">
                        {!! $questionHtml !!}
                    </div>

                    <div class="space-y-2.5" role="group" aria-label="Answer choices">
                        @foreach ($options as $optionIndex => $option)
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 rounded-pill border px-4 py-3.5 text-left transition active:scale-[0.99]"
                                :disabled="submitted"
                                @click="selectAnswer({{ $index }}, {{ $optionIndex }})"
                                :class="optionClass({{ $index }}, {{ $optionIndex }})"
                            >
                                <span
                                    class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-current/20 text-xs font-bold"
                                    x-text="String.fromCharCode(65 + {{ $optionIndex }})"
                                ></span>
                                <span class="study-rich-text text-[15px] font-medium text-text-primary">{!! StudyRichText::toHtml($option) !!}</span>
                            </button>
                        @endforeach
                    </div>

                    @if ($explanationHtml !== '')
                        <div
                            class="rounded-card border border-border-light bg-surface-gray p-4 text-sm text-text-secondary"
                            x-show="submitted"
                            x-cloak
                        >
                            <p class="font-semibold text-text-primary">Explanation</p>
                            <div class="study-rich-text mt-1">{!! $explanationHtml !!}</div>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Navigation --}}
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

                <template x-if="! submitted && current === total - 1">
                    <button
                        type="button"
                        class="inline-flex flex-[1.35] items-center justify-center gap-2 rounded-pill bg-primary-200 px-4 py-3.5 text-sm font-semibold text-white shadow-soft transition hover:bg-primary-300 active:scale-[0.98] disabled:opacity-40"
                        @click="submit()"
                        :disabled="! canSubmit()"
                    >
                        <span>Submit exam</span>
                    </button>
                </template>

                <template x-if="submitted">
                    <button
                        type="button"
                        class="inline-flex flex-[1.35] items-center justify-center gap-2 rounded-pill border border-border-light bg-surface-white px-4 py-3.5 text-sm font-semibold text-text-secondary transition hover:bg-surface-gray active:scale-[0.98]"
                        @click="reset()"
                    >
                        <span>Try again</span>
                    </button>
                </template>

                <button
                    type="button"
                    class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-pill bg-primary-200 px-3 py-3 text-sm font-semibold text-white shadow-soft transition hover:bg-primary-300 active:scale-[0.98] disabled:opacity-40"
                    @click="next()"
                    :disabled="current === total - 1"
                    x-show="submitted || current < total - 1"
                >
                    <span>Next</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>

        <x-study.question-report-modal />
    </div>
@endif
