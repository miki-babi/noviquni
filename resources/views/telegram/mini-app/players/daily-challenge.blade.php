<x-telegram.mini-app.player-layout
    :copy="$copy"
    :title="$title"
    :back-url="$backUrl"
    :next-url="$leaderboardUrl"
    :next-label="$copy->get('daily_challenge.leaderboard_button')"
>
    <div class="space-y-5 px-4 pt-4">
        <div class="space-y-1">
            <p class="text-sm text-text-secondary">
                Streak: <span class="font-semibold text-text-primary">{{ (int) ($streak?->current_streak ?? 0) }}</span>
                @if ($streak?->unlockedBadges())
                    · Badges:
                    @foreach ($streak->unlockedBadges() as $badge)
                        <span class="font-semibold text-primary-200">{{ $badge }}d</span>@if (! $loop->last), @endif
                    @endforeach
                @endif
            </p>
            @if ($attempt)
                <p class="text-sm text-text-secondary">
                    Today: {{ $attempt->score }}/{{ $attempt->total }}
                    · {{ number_format($attempt->duration_ms / 1000, 1) }}s
                </p>
            @endif
        </div>

        @if ($attempt)
            <div class="space-y-3">
                <p class="text-[15px] leading-relaxed text-text-secondary">
                    You already completed today's challenge. Check the leaderboard or come back tomorrow.
                </p>
                <a href="{{ $leaderboardUrl }}" class="tg-btn">{{ $copy->get('daily_challenge.leaderboard_button') }}</a>
                <a href="{{ $premiumUrl }}" class="tg-btn tg-btn-secondary">Unlock premium</a>
            </div>
        @else
            <x-study.quiz
                :payload="$payload"
                :result-url="$resultUrl"
                :track-duration="true"
            />
            <p class="pt-2 text-xs text-text-muted">
                Daily challenge is free. Premium unlocks full explanations and more practice.
                <a href="{{ $premiumUrl }}" class="underline">See premium</a>
            </p>
        @endif
    </div>
</x-telegram.mini-app.player-layout>
