<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$copy->get('menu.premium')"
    :back-url="route('tg.profile')"
>
    <div class="study-page">
        @if ($user->hasActivePremium())
            <div class="study-card study-card--mint study-continue">
                <p class="study-card-title">Premium active until {{ $user->premium_until->toDayDateTimeString() }}</p>
            </div>
        @else
            <div class="study-card study-card--lime study-continue">
                <p class="study-card-title">Premium — full resource access</p>
                <p class="study-card-meta mt-2">Modules, notes, worksheets, quizzes, flashcards, and exams.</p>
                <p class="study-card-meta mt-1">Price: {{ $price }} ETB</p>
                <p class="study-card-meta mt-1">Referrals: {{ $progress }}/{{ $required }}</p>
                @if (filled($urgency))
                    <p class="mt-2 text-sm font-semibold">{{ $urgency }}</p>
                @endif
            </div>

            @if (session('payment_instructions'))
                <div class="study-card study-card--coral study-continue">
                    <p class="whitespace-pre-line text-sm leading-relaxed">{{ session('payment_instructions') }}</p>
                </div>
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
