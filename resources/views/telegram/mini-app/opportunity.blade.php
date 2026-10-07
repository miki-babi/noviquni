<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$opportunity->title"
    :back-url="route('tg.saved', ['tab' => $opportunity->type->savedTabKey()])"
>
    <div class="study-page space-y-4">
        <p class="text-sm font-semibold text-text-secondary">{{ $opportunity->type->label() }}</p>

        @if (filled($opportunity->description))
            <div class="whitespace-pre-wrap text-[15px] leading-relaxed text-text-primary">
                {{ $opportunity->description }}
            </div>
        @endif

        <p class="text-sm tg-hint">
            @if ($opportunity->deadline)
                {{ $copy->get('opportunities.deadline', ['date' => $opportunity->deadline->toFormattedDateString()]) }}
            @else
                {{ $copy->get('opportunities.deadline_none') }}
            @endif
        </p>

        <div class="flex flex-col gap-3">
            @if (filled($opportunity->url))
                <a
                    href="{{ $opportunity->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="tg-btn"
                >
                    {{ $copy->get('opportunities.open_url') }}
                </a>
            @endif

            <form method="post" action="{{ route('tg.saved.opportunities.toggle', $opportunity) }}">
                @csrf
                <button type="submit" class="tg-btn tg-btn-secondary w-full">
                    {{ $isBookmarked ? $copy->get('saved.unsave') : $copy->get('saved.save') }}
                </button>
            </form>
        </div>
    </div>
</x-telegram.mini-app.layout>
