@props([
    'question',
    'index',
])

@php
    $options = array_values(is_array($question['options'] ?? null) ? $question['options'] : []);
    $difficulty = $question['difficulty'] ?? null;
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-text-muted">Question {{ $index + 1 }}</span>
        @if (filled($difficulty))
            <x-ui.badge>{{ ucfirst((string) $difficulty) }}</x-ui.badge>
        @endif
    </div>

    <p class="text-lg font-semibold text-text-primary">{{ $question['question'] ?? '' }}</p>

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
                <span class="text-text-primary">{{ $option }}</span>
            </button>
        @endforeach
    </div>

    <template x-if="submitted && explanations[{{ $index }}]">
        <div class="rounded-card border border-border-light bg-surface-gray p-4 text-sm text-text-secondary">
            <p class="font-semibold text-text-primary">Explanation</p>
            <p class="mt-1" x-text="explanations[{{ $index }}]"></p>
        </div>
    </template>
</div>
