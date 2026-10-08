<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionSource;
use App\Enums\UserEventName;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
        public UserEventService $userEvents,
    ) {}

    public function createPendingPremiumPayment(User $user): Payment
    {
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'amount' => $this->settings->premiumPrice(),
            'currency' => 'ETB',
            'provider' => 'manual',
            'external_ref' => 'PAY-'.strtoupper(Str::random(10)),
            'status' => PaymentStatus::Pending,
            'purpose' => 'premium',
        ]);

        $this->userEvents->log($user, UserEventName::PaymentSubmitted, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'external_ref' => $payment->external_ref,
        ]);

        return $payment;
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

            $payment = $payment->refresh();
            $payment->loadMissing('user');

            if ($payment->user !== null) {
                $this->userEvents->log($payment->user, UserEventName::PaymentApproved, [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ]);
            }

            return $payment;
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
