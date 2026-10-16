@props([
    'question',
    'index',
])

@php
    use App\Support\StudyRichText;

    $options = array_values(is_array($question['options'] ?? null) ? $question['options'] : []);
    $difficulty = $question['difficulty'] ?? null;
    $questionHtml = StudyRichText::toHtml($question['question'] ?? null);
    $explanationHtml = StudyRichText::toHtml($question['explanation'] ?? null);
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-text-muted">Question {{ $index + 1 }}</span>
            @if (filled($difficulty))
                <x-ui.badge>{{ ucfirst((string) $difficulty) }}</x-ui.badge>
            @endif
        </div>
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

    <div class="study-rich-text text-lg font-semibold text-text-primary">
        {!! $questionHtml !!}
    </div>

    <div class="space-y-2" role="group" aria-label="Answer choices">
        @foreach ($options as $optionIndex => $option)
            <button
                type="button"
                class="flex w-full items-start gap-3 rounded-card border px-4 py-3 text-left transition"
                :disabled="submitted"
                @click="selectAnswer({{ $index }}, {{ $optionIndex }})"
                :class="optionClass({{ $index }}, {{ $optionIndex }})"
            >
                <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-border-light text-xs font-bold"
                    x-text="String.fromCharCode(65 + {{ $optionIndex }})"></span>
                <span class="study-rich-text text-text-primary">{!! StudyRichText::toHtml($option) !!}</span>
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
