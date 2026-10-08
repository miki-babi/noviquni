<?php

namespace App\Services;

use App\Enums\ChallengeRewardType;
use App\Enums\ChallengeStatus;
use App\Enums\ReferralPointTransactionType;
use App\Enums\SubscriptionSource;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\User;
use App\Support\TelegramHtml;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChallengeService
{
    public function __construct(
        public ReferralPointService $points,
        public PremiumService $premium,
        public TelegramService $telegram,
    ) {}

    /**
     * @return Collection<int, ChallengeCompletion>
     */
    public function tryAutoRedeem(User $user): Collection
    {
        $completions = collect();

        $challenges = Challenge::query()
            ->redeemable()
            ->whereDoesntHave('completions', fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('cost_points')
            ->orderBy('id')
            ->get();

        foreach ($challenges as $challenge) {
            $user->refresh();

            if ($user->referral_points < $challenge->cost_points) {
                continue;
            }

            try {
                $completion = $this->redeem($user, $challenge);
            } catch (Throwable) {
                continue;
            }

            if ($completion !== null) {
                $completions->push($completion);
            }
        }

        return $completions;
    }

    public function redeem(User $user, Challenge $challenge): ?ChallengeCompletion
    {
        $completion = DB::transaction(function () use ($user, $challenge): ?ChallengeCompletion {
            $lockedChallenge = Challenge::query()->whereKey($challenge->id)->lockForUpdate()->firstOrFail();

            if ($lockedChallenge->status !== ChallengeStatus::Active || ! $lockedChallenge->hasAvailableSlots()) {
                return null;
            }

            $alreadyCompleted = ChallengeCompletion::query()
                ->where('challenge_id', $lockedChallenge->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyCompleted) {
                return null;
            }

            $this->points->debit(
                $user,
                $lockedChallenge->cost_points,
                ReferralPointTransactionType::ChallengeSpent,
                challenge: $lockedChallenge,
            );

            $completion = ChallengeCompletion::query()->create([
                'challenge_id' => $lockedChallenge->id,
                'user_id' => $user->id,
                'points_spent' => $lockedChallenge->cost_points,
                'completed_at' => now(),
            ]);

            $lockedChallenge->increment('winners_count');

            $this->applyReward($user, $lockedChallenge);

            return $completion;
        });

        if ($completion !== null) {
            $this->notifyCompletion($user, $challenge->fresh() ?? $challenge);
        }

        return $completion;
    }

    protected function applyReward(User $user, Challenge $challenge): void
    {
        match ($challenge->reward_type) {
            ChallengeRewardType::PremiumDays => $this->premium->grant(
                $user,
                SubscriptionSource::Challenge,
            ),
            ChallengeRewardType::Message => null,
        };
    }

    protected function notifyCompletion(User $user, Challenge $challenge): void
    {
        if ($user->telegram_id === null) {
            return;
        }

        $title = TelegramHtml::escape($challenge->title);
        $message = filled($challenge->reward_message)
            ? TelegramHtml::escape($challenge->reward_message)
            : 'Your prize has been unlocked.';

        $this->telegram->sendMessage(
            $user->telegram_id,
            "🎉 <b>Challenge complete</b>\n\n<b>{$title}</b>\n{$message}",
        );
    }
}
