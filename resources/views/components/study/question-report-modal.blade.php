@php
    use App\Enums\QuestionReportReason;

    $reasons = collect(QuestionReportReason::cases())
        ->map(fn (QuestionReportReason $reason): array => [
            'value' => $reason->value,
            'label' => $reason->label(),
        ])
        ->values()
        ->all();
@endphp

<div
    x-show="reportOpen"
    x-cloak
    class="tg-modal-overlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="question-report-title"
    @click.self="closeReport()"
    @keydown.escape.window="closeReport()"
>
    <div class="tg-modal" @click.stop>
        <p id="question-report-title" class="tg-modal-title">Report question</p>
        <p class="tg-modal-message">Tell us what’s wrong with question <span x-text="(reportIndex ?? 0) + 1"></span>.</p>

        <div class="mt-3 space-y-3 text-left">
            <label class="block text-sm font-medium text-text-secondary">
                Reason
                <select
                    class="mt-1 w-full rounded-xl border border-border-light bg-surface-white px-3 py-2 text-sm text-text-primary"
                    x-model="reportReason"
                >
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-medium text-text-secondary">
                <span x-text="reportReason === 'other' ? 'Describe the issue (required)' : 'Details (optional)'"></span>
                <textarea
                    class="mt-1 w-full rounded-xl border border-border-light bg-surface-white px-3 py-2 text-sm text-text-primary"
                    rows="3"
                    maxlength="500"
                    x-model="reportNote"
                    :placeholder="reportReason === 'other' ? 'Please describe the problem' : 'Anything that helps us fix it'"
                ></textarea>
            </label>

            <p class="text-sm text-red-500" x-show="reportError" x-text="reportError" x-cloak></p>
        </div>

        <div class="tg-modal-actions">
            <button type="button" class="tg-btn tg-btn-secondary" @click="closeReport()" :disabled="reportBusy">
                Cancel
            </button>
            <button
                type="button"
                class="tg-btn"
                @click="submitReport()"
                :disabled="reportBusy || ! canSubmitReport()"
            >
                <span x-show="! reportBusy">Submit</span>
                <span x-show="reportBusy" x-cloak>Sending…</span>
            </button>
        </div>
    </div>
</div>
