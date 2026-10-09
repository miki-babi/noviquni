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

    public function createPendingPremiumPayment(User $user, string $provider = Payment::PROVIDER_MANUAL): Payment
    {
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'amount' => $this->settings->premiumPrice(),
            'currency' => 'ETB',
            'provider' => $provider,
            'external_ref' => 'PAY-'.strtoupper(Str::random(10)),
            'status' => PaymentStatus::Pending,
            'purpose' => 'premium',
        ]);

        $this->userEvents->log($user, UserEventName::PaymentSubmitted, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'external_ref' => $payment->external_ref,
            'provider' => $payment->provider,
        ]);

        return $payment;
    }

    public function createOrReusePendingPaymentBotPayment(User $user): Payment
    {
        $existing = Payment::query()
            ->where('user_id', $user->id)
            ->where('provider', Payment::PROVIDER_PAYMENT_BOT)
            ->where('purpose', 'premium')
            ->where('status', PaymentStatus::Pending)
            ->whereNull('fulfilled_at')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createPendingPremiumPayment($user, Payment::PROVIDER_PAYMENT_BOT);
    }

    public function verify(Payment $payment, User $admin): Payment
    {
        return $this->fulfillVerified($payment, $admin);
    }

    public function verifyViaPaymentBot(Payment $payment): Payment
    {
        return $this->fulfillVerified($payment, verifier: null, verifiedVia: 'payment_bot');
    }

    public function reject(Payment $payment, User $admin): Payment
    {
        return $this->markRejected($payment, $admin);
    }

    public function rejectViaPaymentBot(Payment $payment): Payment
    {
        return $this->markRejected($payment, verifier: null, verifiedVia: 'payment_bot');
    }

    public function instructionsFor(Payment $payment): string
    {
        return str_replace(
            ['{amount}', '{reference}'],
            [(string) $payment->amount, $payment->external_ref],
            $this->settings->paymentInstructions(),
        );
    }

    protected function fulfillVerified(Payment $payment, ?User $verifier = null, ?string $verifiedVia = null): Payment
    {
        if ($payment->status === PaymentStatus::Verified) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $verifier, $verifiedVia): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->fulfilled_at !== null || $locked->status === PaymentStatus::Verified) {
                return $locked;
            }

            $meta = $locked->meta ?? [];
            if ($verifiedVia !== null) {
                $meta['verified_via'] = $verifiedVia;
            }

            $locked->update([
                'status' => PaymentStatus::Verified,
                'verified_at' => now(),
                'verified_by' => $verifier?->id,
                'fulfilled_at' => $locked->fulfilled_at ?? now(),
                'meta' => $meta,
            ]);

            $this->premium->grant(
                $locked->user,
                SubscriptionSource::Payment,
                $locked->id,
            );

            $locked = $locked->refresh();
            $locked->loadMissing('user');

            if ($locked->user !== null) {
                $this->userEvents->log($locked->user, UserEventName::PaymentApproved, [
                    'payment_id' => $locked->id,
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'provider' => $locked->provider,
                ]);
            }

            return $locked;
        });
    }

    protected function markRejected(Payment $payment, ?User $verifier = null, ?string $verifiedVia = null): Payment
    {
        $meta = $payment->meta ?? [];
        if ($verifiedVia !== null) {
            $meta['verified_via'] = $verifiedVia;
        }

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_at' => now(),
            'verified_by' => $verifier?->id,
            'meta' => $meta,
        ]);

        return $payment->fresh() ?? $payment;
    }
}
