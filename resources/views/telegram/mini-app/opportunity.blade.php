<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$opportunity->title"
    :back-url="route('tg.saved', ['tab' => $opportunity->type->savedTabKey()])"
>
    <div class="study-page space-y-4">
        <p class="text-sm font-semibold text-text-secondary">{{ $opportunity->type->label() }}</p>

        @if ($opportunity->hasVerifiedPartner())
            <p class="rounded-2xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-semibold text-primary-300">
                {{ $copy->get('opportunities.verified_partner', ['partner' => $opportunity->partner_name]) }}
            </p>
        @endif

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

        @if (session('status'))
            <p class="text-sm font-semibold text-primary-300">{{ session('status') }}</p>
        @endif

        @if (session('show_onboarding_link'))
            <a href="{{ $botOnboardingUrl }}" target="_blank" rel="noopener noreferrer" class="tg-btn">
                {{ $copy->get('opportunities.open_bot_to_onboard') }}
            </a>
        @endif

        <div class="flex flex-col gap-3">
            @if (filled($opportunity->url) && $onboardingComplete)
                <a
                    href="{{ $opportunity->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="tg-btn"
                >
                    {{ $copy->get('opportunities.open_url') }}
                </a>
            @elseif (filled($opportunity->url))
                <a href="{{ $botOnboardingUrl }}" target="_blank" rel="noopener noreferrer" class="tg-btn">
                    {{ $copy->get('opportunities.open_url') }}
                </a>
            @endif

            @if ($opportunity->hasVerifiedPartner())
                <form method="post" action="{{ route('tg.opportunities.guidance', $opportunity) }}">
                    @csrf
                    <button type="submit" class="tg-btn tg-btn-secondary w-full">
                        {{ $copy->get('opportunities.request_guidance') }}
                    </button>
                </form>
            @endif

            <form method="post" action="{{ route('tg.saved.opportunities.toggle', $opportunity) }}">
                @csrf
                <button type="submit" class="tg-btn tg-btn-secondary w-full">
                    {{ $isBookmarked ? $copy->get('saved.unsave') : $copy->get('saved.save') }}
                </button>
            </form>

            <a
                href="{{ $shareUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="tg-btn tg-btn-secondary"
            >
                {{ $copy->get('opportunities.share') }}
            </a>
        </div>
    </div>
</x-telegram.mini-app.layout>
