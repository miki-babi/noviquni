<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVerifyCheckoutWebhookJob;
use App\Models\VerifyCheckoutWebhookEvent;
use App\Services\VerifyCheckout\VerifyCheckoutClient;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class VerifyCheckoutWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 262_144;

    public function __invoke(Request $request, VerifyCheckoutClient $client): Response
    {
        if (! $request->isMethod('POST')) {
            return response('Method Not Allowed', 405);
        }

        $contentType = (string) $request->header('Content-Type', '');

        if (! str_contains(strtolower($contentType), 'application/json')) {
            return response('Unsupported Media Type', 415);
        }

        $contentLength = $request->header('Content-Length');

        if (is_numeric($contentLength) && (int) $contentLength > self::MAX_BODY_BYTES) {
            return response('Payload Too Large', 413);
        }

        $rawBody = $request->getContent();

        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return response('Payload Too Large', 413);
        }

        $secret = (string) config('services.verify_checkout.webhook_secret');

        if ($secret === '') {
            Log::error('verify_checkout.webhook_secret_missing');

            return response('Service Unavailable', 503);
        }

        $timestamp = (string) $request->header('VerifyCheckout-Timestamp', '');
        $signature = (string) $request->header('VerifyCheckout-Signature', '');
        $eventIdHeader = (string) $request->header('VerifyCheckout-Event-Id', '');
        $eventTypeHeader = (string) $request->header('VerifyCheckout-Event', '');
        $deliveryId = $request->header('VerifyCheckout-Delivery-Id');
        $apiVersion = (string) $request->header('VerifyCheckout-Api-Version', '');

        if ($apiVersion !== '' && $apiVersion !== (string) config('services.verify_checkout.api_version')) {
            return response('Invalid API version', 400);
        }

        if (! $client->verifyWebhookSignature($rawBody, $timestamp, $signature)) {
            return response('Invalid signature', 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response('Invalid JSON', 400);
        }

        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response('Invalid event', 400);
        }

        if ($eventIdHeader !== '' && $eventIdHeader !== $eventId) {
            return response('Event ID mismatch', 400);
        }

        if ($eventTypeHeader !== '' && $eventTypeHeader !== $eventType) {
            return response('Event type mismatch', 400);
        }

        $depositId = data_get($payload, 'data.object.id');
        $sequence = data_get($payload, 'sequence');

        try {
            $event = VerifyCheckoutWebhookEvent::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'delivery_id' => is_string($deliveryId) ? $deliveryId : null,
                'deposit_id' => is_string($depositId) ? $depositId : null,
                'sequence' => is_numeric($sequence) ? (int) $sequence : null,
                'payload' => $payload,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return response('OK', 200);
            }

            throw $exception;
        }

        ProcessVerifyCheckoutWebhookJob::dispatch($event->id);

        return response('OK', 200);
    }

    protected function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
