<x-telegram.mini-app.layout :copy="$copy" :active-nav="$activeNav" :title="$copy->get('keyboard.premium')">
    <div class="space-y-5">
        @if ($user->hasActivePremium())
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
                Premium active until {{ $user->premium_until->toDayDateTimeString() }}
            </div>
        @else
            <div class="rounded-2xl border border-border-light px-4 py-4 text-sm leading-relaxed space-y-2">
                <p class="font-semibold">⭐ Premium — full resource access</p>
                <p>Modules, notes, worksheets, quizzes, flashcards, and exams.</p>
                <p>Price: {{ $price }} ETB</p>
                <p>Referrals: {{ $progress }}/{{ $required }}</p>
                @if (filled($urgency))
                    <p class="text-amber-800">{{ $urgency }}</p>
                @endif
            </div>

            @if (session('payment_instructions'))
                <div class="whitespace-pre-line rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950">
                    {{ session('payment_instructions') }}
                </div>
            @endif

            <form method="POST" action="{{ route('tg.premium.pay') }}">
                @csrf
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white">
                    Pay now
                </button>
            </form>
        @endif
    </div>
</x-telegram.mini-app.layout>
