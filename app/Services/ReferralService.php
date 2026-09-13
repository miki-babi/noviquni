<?php

namespace App\Services;

use App\Enums\ReferralStatus;
use App\Enums\RewardStatus;
use App\Enums\SubscriptionSource;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
    ) {}

    public function attributeReferral(User $referred, string $referralCode): ?Referral
    {
        $referrer = User::query()
            ->students()
            ->where('referral_code', $referralCode)
            ->where('is_active', true)
            ->first();

        if ($referrer === null || $referrer->id === $referred->id) {
            return null;
        }

        if ($referred->referred_by_user_id !== null) {
            return null;
        }

        return DB::transaction(function () use ($referrer, $referred): Referral {
            $referred->update(['referred_by_user_id' => $referrer->id]);

            $referral = Referral::query()->create([
                'referrer_id' => $referrer->id,
                'referred_id' => $referred->id,
                'status' => ReferralStatus::Qualified,
            ]);

            ReferralReward::query()->create([
                'referral_id' => $referral->id,
                'user_id' => $referrer->id,
                'amount' => $this->settings->freeReferralReward(),
                'status' => RewardStatus::Qualified,
            ]);

            $this->maybeUnlockPremium($referrer);

            return $referral;
        });
    }

    public function maybeUnlockPremium(User $referrer): void
    {
        $qualifiedCount = Referral::query()
            ->where('referrer_id', $referrer->id)
            ->whereIn('status', [ReferralStatus::Qualified, ReferralStatus::Completed])
            ->count();

        if ($qualifiedCount < $this->settings->requiredReferrals()) {
            return;
        }

        if ($referrer->hasActivePremium()) {
            return;
        }

        $this->premium->grant($referrer, SubscriptionSource::Referral);

        Referral::query()
            ->where('referrer_id', $referrer->id)
            ->where('status', ReferralStatus::Qualified)
            ->limit($this->settings->requiredReferrals())
            ->update(['status' => ReferralStatus::Completed]);
    }

    public function upgradeRewardForPremiumReferral(User $referred): void
    {
        $referral = Referral::query()->where('referred_id', $referred->id)->first();

        if ($referral === null || $referral->reward === null) {
            return;
        }

        if (in_array($referral->reward->status, [RewardStatus::Paid, RewardStatus::Rejected], true)) {
            return;
        }

        $referral->reward->update([
            'amount' => $this->settings->premiumReferralReward(),
            'status' => RewardStatus::Qualified,
        ]);
    }

    public function referralLink(User $user): string
    {
        $bot = config('services.telegram.bot_username', 'noviquni_bot');

        return "https://t.me/{$bot}?start={$user->referral_code}";
    }

    public function qualifiedCount(User $user): int
    {
        return Referral::query()
            ->where('referrer_id', $user->id)
            ->whereIn('status', [ReferralStatus::Qualified, ReferralStatus::Completed])
            ->count();
    }
}
