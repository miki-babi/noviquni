<?php

namespace App\Services;

use App\Enums\ReferralStatus;
use App\Enums\SubscriptionSource;
use App\Enums\UserEventName;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
        public ReferralPointService $points,
        public ChallengeService $challenges,
        public UserEventService $userEvents,
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

            $this->points->creditForReferral($referrer, $referral);
            $this->maybeUnlockPremium($referrer->fresh());
            $this->challenges->tryAutoRedeem($referrer->fresh());

            $this->userEvents->log($referred, UserEventName::ReferralJoined, [
                'referrer_id' => $referrer->id,
                'referral_code' => $referrer->referral_code,
                'referral_id' => $referral->id,
            ]);

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
