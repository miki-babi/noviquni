<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionSource;
use App\Enums\UserEventName;
use App\Models\Payment;
use App\Models\User;
use App\Services\VerifyCheckout\VerifyCheckoutClient;
use App\Services\VerifyCheckout\VerifyCheckoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        public SettingsService $settings,
        public PremiumService $premium,
        public UserEventService $userEvents,
        public VerifyCheckoutClient $verifyCheckout,
    ) {}

    public function createPendingPremiumPayment(User $user): Payment
    {
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'amount' => $this->settings->premiumPrice(),
            'currency' => 'ETB',
            'provider' => Payment::PROVIDER_MANUAL,
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

    /**
     * @throws VerifyCheckoutException
     */
    public function startVerifyCheckoutPremium(User $user): Payment
    {
        $existing = Payment::query()
            ->where('user_id', $user->id)
            ->where('provider', Payment::PROVIDER_VERIFY_CHECKOUT)
            ->where('purpose', 'premium')
            ->where('status', PaymentStatus::Pending)
            ->whereNull('fulfilled_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('idempotency_key')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            $deposit = $this->verifyCheckout->createDepositWithRetry(
                $this->createDepositPayload($user, $existing),
                (string) $existing->idempotency_key,
            );

            return $this->applyDepositCreateResponse($existing, $deposit, $user);
        }

        $idempotencyKey = 'checkout_'.(string) Str::uuid();

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'amount' => $this->settings->premiumPrice(),
            'currency' => 'ETB',
            'provider' => Payment::PROVIDER_VERIFY_CHECKOUT,
            'external_ref' => 'VC-'.strtoupper(Str::random(10)),
            'idempotency_key' => $idempotencyKey,
            'status' => PaymentStatus::Pending,
            'purpose' => 'premium',
        ]);

        $this->userEvents->log($user, UserEventName::PaymentSubmitted, [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => Payment::PROVIDER_VERIFY_CHECKOUT,
        ]);

        $deposit = $this->verifyCheckout->createDepositWithRetry(
            $this->createDepositPayload($user, $payment),
            $idempotencyKey,
        );

        return $this->applyDepositCreateResponse($payment, $deposit, $user);
    }

    /**
     * @param  array<string, mixed>  $deposit
     */
    public function syncFromDeposit(Payment $payment, array $deposit): Payment
    {
        $status = (string) ($deposit['status'] ?? '');

        $meta = $payment->meta ?? [];
        if (filled($deposit['support_reference'] ?? null)) {
            $meta['support_reference'] = $deposit['support_reference'];
        }

        $payment->update([
            'deposit_id' => $deposit['id'] ?? $payment->deposit_id,
            'deposit_status' => $status !== '' ? $status : $payment->deposit_status,
            'expires_at' => filled($deposit['expires_at'] ?? null) ? $deposit['expires_at'] : $payment->expires_at,
            'amount' => filled($deposit['amount'] ?? null) ? $deposit['amount'] : $payment->amount,
            'meta' => $meta,
        ]);

        $payment = $payment->fresh() ?? $payment;

        if ($status === 'succeeded') {
            return $this->fulfillSucceeded($payment);
        }

        if (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update([
                    'status' => PaymentStatus::Rejected,
                    'verified_at' => now(),
                ]);
            }
        }

        return $payment->fresh() ?? $payment;
    }

    public function fulfillSucceeded(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->fulfilled_at !== null || $locked->status === PaymentStatus::Verified) {
                return $locked;
            }

            if ($locked->deposit_status !== null && $locked->deposit_status !== 'succeeded') {
                return $locked;
            }

            $locked->update([
                'status' => PaymentStatus::Verified,
                'deposit_status' => 'succeeded',
                'verified_at' => now(),
                'fulfilled_at' => now(),
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
                    'deposit_id' => $locked->deposit_id,
                ]);
            }

            return $locked;
        });
    }

    public function reconcileStaleVerifyCheckoutPayments(): int
    {
        $payments = Payment::query()
            ->where('provider', Payment::PROVIDER_VERIFY_CHECKOUT)
            ->where('status', PaymentStatus::Pending)
            ->whereNull('fulfilled_at')
            ->whereNotNull('deposit_id')
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $updated = 0;

        foreach ($payments as $payment) {
            try {
                $deposit = $this->verifyCheckout->getDeposit((string) $payment->deposit_id);
                $this->syncFromDeposit($payment, $deposit);
                $updated++;
            } catch (VerifyCheckoutException $exception) {
                Log::warning('verify_checkout.reconcile_failed', [
                    'payment_id' => $payment->id,
                    'deposit_id' => $payment->deposit_id,
                    'error_code' => $exception->errorCode,
                    'request_id' => $exception->requestId,
                ]);
            }
        }

        return $updated;
    }

    public function reconcilePayment(Payment $payment): Payment
    {
        if (! $payment->isVerifyCheckout() || blank($payment->deposit_id)) {
            return $payment;
        }

        $deposit = $this->verifyCheckout->getDeposit((string) $payment->deposit_id);

        return $this->syncFromDeposit($payment, $deposit);
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
                'fulfilled_at' => $payment->fulfilled_at ?? now(),
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

    /**
     * @return array{merchant_customer_id: string, amount: string, currency: string, return_url: string}
     */
    protected function createDepositPayload(User $user, Payment $payment): array
    {
        $amount = number_format((float) $payment->amount, 2, '.', '');

        return [
            'merchant_customer_id' => 'user_'.$user->id,
            'amount' => $amount,
            'currency' => 'ETB',
            'return_url' => URL::signedRoute('tg.premium.return', ['payment' => $payment->id]),
        ];
    }

    /**
     * @param  array<string, mixed>  $deposit
     */
    protected function applyDepositCreateResponse(Payment $payment, array $deposit, User $user): Payment
    {
        $checkoutUrl = (string) ($deposit['checkout_url'] ?? '');

        if ($checkoutUrl === '') {
            throw new VerifyCheckoutException(
                message: 'Verify Checkout did not return a checkout URL.',
                errorCode: 'invalid_request',
            );
        }

        $meta = $payment->meta ?? [];
        if (filled($deposit['support_reference'] ?? null)) {
            $meta['support_reference'] = $deposit['support_reference'];
        }
        if (filled($deposit['merchant_order_id'] ?? null)) {
            $meta['merchant_order_id'] = $deposit['merchant_order_id'];
        }

        $payment->update([
            'deposit_id' => $deposit['id'],
            'deposit_status' => $deposit['status'] ?? 'awaiting_transfer',
            'expires_at' => $deposit['expires_at'] ?? now()->addHour(),
            'amount' => $deposit['amount'] ?? $payment->amount,
            'meta' => $meta,
        ]);

        $payment = $payment->fresh() ?? $payment;
        $payment->checkoutUrl = $checkoutUrl;

        Log::info('verify_checkout.deposit_created', [
            'payment_id' => $payment->id,
            'deposit_id' => $payment->deposit_id,
            'user_id' => $user->id,
            'status' => $payment->deposit_status,
        ]);

        return $payment;
    }
}
