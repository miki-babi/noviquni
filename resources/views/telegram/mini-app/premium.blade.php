<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('menu.premium')"
    :back-url="route('tg.profile')"
>
    <div class="study-page space-y-4">
        @if ($user->hasActivePremium())
            <div class="study-card study-card--mint study-continue">
                <p class="study-card-title">Premium is active</p>
            </div>
        @else
            <div class="study-card study-card--lime study-continue space-y-2">
                <p class="study-card-title">{{ $pitchTitle }}</p>
                <p class="whitespace-pre-line text-sm leading-relaxed text-text-secondary">{{ $pitchBody }}</p>
                <p class="study-card-meta mt-2">Price: {{ $price }} ETB</p>
                <p class="study-card-meta mt-1">Referrals: {{ $progress }}/{{ $required }}</p>
                @if (filled($urgency))
                    <p class="mt-2 text-sm font-semibold">{{ $urgency }}</p>
                @endif
            </div>

            @if ($paymentBotUrl)
                <a href="{{ $paymentBotUrl }}" target="_blank" rel="noopener noreferrer" class="tg-btn">
                    {{ $copy->get('opportunities.guidance_unlock_premium') }}
                </a>
            @else
                <p class="text-sm font-semibold text-primary-300">Payment is temporarily unavailable. Please try again later.</p>
            @endif
        @endif
    </div>
</x-telegram.mini-app.layout>
