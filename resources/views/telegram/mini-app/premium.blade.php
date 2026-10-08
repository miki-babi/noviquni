<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('menu.premium')"
    :back-url="route('tg.profile')"
>
    <div class="study-page space-y-4">
        @if ($user->hasActivePremium())
            <div class="study-card study-card--mint study-continue">
                <p class="study-card-title">Premium active until {{ $user->premium_until->toDayDateTimeString() }}</p>
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

            @if (session('status'))
                <p class="text-sm font-semibold text-primary-300">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('tg.premium.pay') }}">
                @csrf
                <button type="submit" class="tg-btn">
                    Pay now
                </button>
            </form>
        @endif
    </div>
</x-telegram.mini-app.layout>
