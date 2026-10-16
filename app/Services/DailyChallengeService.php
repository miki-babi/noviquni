<?php

namespace App\Services;

use App\Models\DailyChallenge;
use App\Models\DailyChallengeAttempt;
use App\Models\User;
use App\Models\UserStreak;
use App\Support\DailyChallengeCalendar;
use App\Support\StudyRichText;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DailyChallengeService
{
    public function today(?CarbonInterface $at = null): ?DailyChallenge
    {
        return DailyChallenge::query()
            ->whereDate('challenge_date', DailyChallengeCalendar::todayDateString($at))
            ->first();
    }

    public function userHasCompletedTodaysDailyChallenge(?User $user, ?CarbonInterface $at = null): bool
    {
        if ($user === null) {
            return false;
        }

        $challenge = $this->today($at);

        if ($challenge === null) {
            return false;
        }

        return DailyChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->where('daily_challenge_id', $challenge->id)
            ->exists();
    }

    public function attemptFor(User $user, DailyChallenge $challenge): ?DailyChallengeAttempt
    {
        return DailyChallengeAttempt::query()
            ->where('user_id', $user->id)
            ->where('daily_challenge_id', $challenge->id)
            ->first();
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function recordAttempt(
        User $user,
        DailyChallenge $challenge,
        array $answers,
        int $durationMs,
    ): DailyChallengeAttempt {
        $existing = $this->attemptFor($user, $challenge);

        if ($existing !== null) {
            return $existing;
        }

        $questions = $challenge->playerPayload()['questions'];

        if ($questions === []) {
            throw new InvalidArgumentException('This daily challenge has no scorable questions.');
        }

        $score = 0;

        foreach ($questions as $index => $question) {
            $correctIndex = (int) ($question['answerIndex'] ?? 0);
            $rawSelected = $answers[$index] ?? $answers[(string) $index] ?? null;
            $selectedIndex = is_numeric($rawSelected) ? (int) $rawSelected : null;

            if ($selectedIndex !== null && $selectedIndex === $correctIndex) {
                $score++;
            }
        }

        $total = count($questions);
        $durationMs = max(0, $durationMs);
        $completedOn = $challenge->challenge_date->toDateString();

        return DB::transaction(function () use ($user, $challenge, $score, $total, $durationMs, $completedOn): DailyChallengeAttempt {
            $attempt = DailyChallengeAttempt::query()->create([
                'user_id' => $user->id,
                'daily_challenge_id' => $challenge->id,
                'score' => $score,
                'total' => $total,
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $this->updateStreak($user, $completedOn);

            return $attempt;
        });
    }

    public function updateStreak(User $user, string $completedOn): UserStreak
    {
        $streak = UserStreak::query()->firstOrNew(['user_id' => $user->id]);

        if ($streak->last_completed_on?->toDateString() === $completedOn) {
            return $streak;
        }

        $completed = DailyChallengeCalendar::now()->parse($completedOn)->startOfDay();
        $yesterday = $completed->copy()->subDay()->toDateString();

        if ($streak->last_completed_on?->toDateString() === $yesterday) {
            $streak->current_streak = ((int) $streak->current_streak) + 1;
        } else {
            $streak->current_streak = 1;
        }

        $streak->longest_streak = max((int) $streak->longest_streak, (int) $streak->current_streak);
        $streak->last_completed_on = $completedOn;
        $streak->badges = $this->badgesForStreak((int) $streak->current_streak, $streak->unlockedBadges());
        $streak->save();

        return $streak;
    }

    /**
     * @param  list<int>  $existing
     * @return list<int>
     */
    protected function badgesForStreak(int $currentStreak, array $existing): array
    {
        $unlocked = $existing;

        foreach (UserStreak::BadgeThresholds as $threshold) {
            if ($currentStreak >= $threshold && ! in_array($threshold, $unlocked, true)) {
                $unlocked[] = $threshold;
            }
        }

        sort($unlocked);

        return array_values($unlocked);
    }

    public function streakFor(User $user): ?UserStreak
    {
        return UserStreak::query()->where('user_id', $user->id)->first();
    }

    /**
     * @return array{
     *     rows: Collection<int, array{rank: int, user_id: int, name: string, score: int, total: int, duration_ms: int}>,
     *     user_rank: int|null,
     *     user_attempt: DailyChallengeAttempt|null
     * }
     */
    public function leaderboard(DailyChallenge $challenge, ?User $user = null, int $limit = 20): array
    {
        $attempts = DailyChallengeAttempt::query()
            ->with('user:id,name')
            ->where('daily_challenge_id', $challenge->id)
            ->orderByDesc('score')
            ->orderBy('duration_ms')
            ->orderBy('completed_at')
            ->get();

        $rows = $attempts
            ->take($limit)
            ->values()
            ->map(function (DailyChallengeAttempt $attempt, int $index): array {
                return [
                    'rank' => $index + 1,
                    'user_id' => $attempt->user_id,
                    'name' => $attempt->user?->name ?? 'Student',
                    'score' => $attempt->score,
                    'total' => $attempt->total,
                    'duration_ms' => $attempt->duration_ms,
                ];
            });

        $userAttempt = $user !== null
            ? $attempts->firstWhere('user_id', $user->id)
            : null;

        $userRank = null;

        if ($userAttempt !== null) {
            $userRank = $attempts->search(
                fn (DailyChallengeAttempt $attempt): bool => $attempt->id === $userAttempt->id
            );

            $userRank = $userRank === false ? null : $userRank + 1;
        }

        return [
            'rows' => $rows,
            'user_rank' => $userRank,
            'user_attempt' => $userAttempt,
        ];
    }

    /**
     * Audience for the 18:00 reminder: active students with Telegram who have not completed today.
     *
     * @return Builder<User>
     */
    public function reminderAudienceQuery(?CarbonInterface $at = null)
    {
        $challenge = $this->today($at);

        $query = User::query()
            ->students()
            ->active()
            ->where('notifications_enabled', true)
            ->whereNotNull('telegram_id');

        if ($challenge === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereDoesntHave(
            'dailyChallengeAttempts',
            fn ($builder) => $builder->where('daily_challenge_id', $challenge->id)
        );
    }

    /**
     * Plain-text preview for debugging / admin; not used for scoring.
     */
    public function questionPreview(array $question): string
    {
        return StudyRichText::toPlain($question['question'] ?? '') ?: 'Question';
    }
}
