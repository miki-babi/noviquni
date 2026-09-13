<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionSource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
        public ReferralService $referrals,
    ) {}

    public function createPendingPremiumPayment(User $user): Payment
    {
        return Payment::query()->create([
            'user_id' => $user->id,
            'amount' => $this->settings->premiumPrice(),
            'currency' => 'ETB',
            'provider' => 'manual',
            'external_ref' => 'PAY-'.strtoupper(Str::random(10)),
            'status' => PaymentStatus::Pending,
            'purpose' => 'premium',
        ]);
    }

    public function verify(Payment $payment, User $admin): Payment
    {
        if ($payment->status === PaymentStatus::Verified) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $admin): Payment {
            $payment->update([
                'status' => PaymentStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);

            $this->premium->grant(
                $payment->user,
                SubscriptionSource::Payment,
                $payment->id,
            );

            $this->referrals->upgradeRewardForPremiumReferral($payment->user);

            return $payment->refresh();
        });
    }

    public function reject(Payment $payment, User $admin): Payment
    {
        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        return $payment;
    }

    public function instructionsFor(Payment $payment): string
    {
        return str_replace(
            ['{amount}', '{reference}'],
            [(string) $payment->amount, $payment->external_ref],
            $this->settings->paymentInstructions(),
        );
    }
}
