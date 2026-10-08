<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\VerifyCheckoutWebhookEvent;
use App\Services\PaymentService;
use App\Services\VerifyCheckout\VerifyCheckoutClient;
use App\Services\VerifyCheckout\VerifyCheckoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessVerifyCheckoutWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookEventId) {}

    public function handle(PaymentService $payments, VerifyCheckoutClient $client): void
    {
        $event = VerifyCheckoutWebhookEvent::query()->find($this->webhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        if ($event->event_type === 'webhook.test') {
            $event->update(['processed_at' => now()]);

            return;
        }

        $depositId = $event->deposit_id;

        if (blank($depositId)) {
            $depositId = data_get($event->payload, 'data.object.id');
        }

        if (blank($depositId)) {
            $event->update(['processed_at' => now()]);

            return;
        }

        $payment = Payment::query()
            ->where('provider', Payment::PROVIDER_VERIFY_CHECKOUT)
            ->where('deposit_id', $depositId)
            ->first();

        if ($payment === null) {
            Log::warning('verify_checkout.webhook_unknown_deposit', [
                'event_id' => $event->event_id,
                'deposit_id' => $depositId,
            ]);
            $event->update(['processed_at' => now()]);

            return;
        }

        try {
            $deposit = $client->getDeposit((string) $depositId);
            $payments->syncFromDeposit($payment, $deposit);
        } catch (VerifyCheckoutException $exception) {
            Log::warning('verify_checkout.webhook_sync_failed', [
                'event_id' => $event->event_id,
                'deposit_id' => $depositId,
                'error_code' => $exception->errorCode,
                'request_id' => $exception->requestId,
            ]);

            throw $exception;
        }

        $event->update(['processed_at' => now()]);
    }
}
