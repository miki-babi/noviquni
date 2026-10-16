<x-telegram.mini-app.player-layout
    :copy="$copy"
    :title="$title"
    :back-url="$backUrl"
>
    <div class="space-y-5 px-4 pt-4 pb-6">
        <div class="space-y-1">
            <p class="text-sm text-text-secondary">
                {{ $challenge->challenge_date->toFormattedDateString() }}
            </p>
            @if ($userRank !== null && $userAttempt)
                <p class="text-sm text-text-secondary">
                    Your rank: <span class="font-semibold text-text-primary">#{{ $userRank }}</span>
                    · {{ $userAttempt->score }}/{{ $userAttempt->total }}
                    · {{ number_format($userAttempt->duration_ms / 1000, 1) }}s
                </p>
            @endif
            <p class="text-sm text-text-secondary">
                Streak: <span class="font-semibold text-text-primary">{{ (int) ($streak?->current_streak ?? 0) }}</span>
            </p>
        </div>

        @if ($rows->isEmpty())
            <p class="text-[15px] leading-relaxed text-text-secondary">No scores yet today. Be the first.</p>
            <a href="{{ $playUrl }}" class="tg-btn">{{ $copy->get('daily_challenge.open_button') }}</a>
        @else
            <ul class="space-y-3">
                @foreach ($rows as $row)
                    <li class="flex items-center justify-between gap-3 text-sm {{ ($row['user_id'] ?? null) === $user->id ? 'font-semibold text-primary-200' : 'text-text-primary' }}">
                        <span class="min-w-0 truncate">
                            #{{ $row['rank'] }} {{ $row['name'] }}
                        </span>
                        <span class="shrink-0 tabular-nums text-text-secondary">
                            {{ $row['score'] }}/{{ $row['total'] }}
                            · {{ number_format($row['duration_ms'] / 1000, 1) }}s
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        <a href="{{ $premiumUrl }}" class="tg-btn tg-btn-secondary">Unlock premium</a>
    </div>
</x-telegram.mini-app.player-layout>
