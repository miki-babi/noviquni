<?php

namespace App\Services;

use App\Enums\ReferralPointTransactionType;
use App\Models\Challenge;
use App\Models\Referral;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReferralPointService
{
    public function __construct(public SettingsService $settings) {}

    public function creditForReferral(User $user, Referral $referral): ReferralPointTransaction
    {
        return $this->credit(
            $user,
            $this->settings->pointsPerReferral(),
            ReferralPointTransactionType::ReferralEarned,
            referral: $referral,
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function credit(
        User $user,
        int $amount,
        ReferralPointTransactionType $type,
        ?Referral $referral = null,
        ?Challenge $challenge = null,
        ?array $meta = null,
    ): ReferralPointTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $referral, $challenge, $meta): ReferralPointTransaction {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'referral_points' => $locked->referral_points + $amount,
            ]);

            $transaction = ReferralPointTransaction::query()->create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'type' => $type,
                'referral_id' => $referral?->id,
                'challenge_id' => $challenge?->id,
                'meta' => $meta,
            ]);

            $user->setAttribute('referral_points', $locked->referral_points);

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function debit(
        User $user,
        int $amount,
        ReferralPointTransactionType $type,
        ?Challenge $challenge = null,
        ?array $meta = null,
    ): ReferralPointTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $type, $challenge, $meta): ReferralPointTransaction {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->referral_points < $amount) {
                throw new InvalidArgumentException('Insufficient referral points.');
            }

            $locked->update([
                'referral_points' => $locked->referral_points - $amount,
            ]);

            $transaction = ReferralPointTransaction::query()->create([
                'user_id' => $locked->id,
                'amount' => -$amount,
                'type' => $type,
                'challenge_id' => $challenge?->id,
                'meta' => $meta,
            ]);

            $user->setAttribute('referral_points', $locked->referral_points);

            return $transaction;
        });
    }
}
