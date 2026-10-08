<?php

namespace App\Services\VerifyCheckout;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyCheckoutClient
{
    /**
     * @param  array{merchant_customer_id: string, amount: string, currency: string, return_url: string, payment_method?: string}  $payload
     * @return array<string, mixed>
     */
    public function createDeposit(array $payload, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/v1/deposits', $payload);

        return $this->depositFromResponse($response, [200, 201]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeposit(string $depositId): array
    {
        $response = $this->request()->get('/v1/deposits/'.$depositId);

        return $this->depositFromResponse($response, [200]);
    }

    public function verifyWebhookSignature(string $rawBody, string $timestamp, string $signature): bool
    {
        $secret = (string) config('services.verify_checkout.webhook_secret');

        if ($secret === '' || ! preg_match('/^\d{10,13}$/', $timestamp) || ! preg_match('/^v1=[a-f0-9]{64}$/', $signature)) {
            return false;
        }

        $timestampNumber = (int) $timestamp;
        $timestampMs = strlen($timestamp) >= 13 ? $timestampNumber : $timestampNumber * 1000;

        if (abs((int) (microtime(true) * 1000) - $timestampMs) > 300_000) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret, true);
        $received = hex2bin(substr($signature, 3));

        if ($received === false || strlen($received) !== strlen($expected)) {
            return false;
        }

        return hash_equals($expected, $received);
    }

    protected function request(): PendingRequest
    {
        $apiKey = (string) config('services.verify_checkout.api_key');

        if ($apiKey === '') {
            throw new VerifyCheckoutException(
                message: 'Verify Checkout API key is not configured.',
                errorCode: 'authentication_required',
            );
        }

        return Http::baseUrl(rtrim((string) config('services.verify_checkout.base_url'), '/'))
            ->withToken($apiKey)
            ->withHeaders([
                'VerifyCheckout-Version' => (string) config('services.verify_checkout.api_version'),
                'Accept' => 'application/json',
            ])
            ->connectTimeout((int) config('services.verify_checkout.connect_timeout'))
            ->timeout((int) config('services.verify_checkout.timeout'))
            ->acceptJson()
            ->asJson();
    }

    /**
     * @param  list<int>  $okStatuses
     * @return array<string, mixed>
     */
    protected function depositFromResponse(Response $response, array $okStatuses): array
    {
        if (! in_array($response->status(), $okStatuses, true)) {
            throw $this->exceptionFromResponse($response);
        }

        $json = $response->json();
        $data = is_array($json) ? ($json['data'] ?? null) : null;

        if (! is_array($data) || blank($data['id'] ?? null)) {
            throw new VerifyCheckoutException(
                message: 'Verify Checkout response was missing deposit data.',
                requestId: is_array($json) ? data_get($json, 'meta.requestId') : null,
                status: $response->status(),
            );
        }

        return $data;
    }

    protected function exceptionFromResponse(Response $response): VerifyCheckoutException
    {
        $json = $response->json();
        $error = is_array($json) ? ($json['error'] ?? []) : [];
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $message = is_array($error) && filled($error['message'] ?? null)
            ? (string) $error['message']
            : 'Verify Checkout request failed.';
        $retryable = is_array($error) ? (bool) ($error['retryable'] ?? false) : false;
        $requestId = is_array($json) ? data_get($json, 'meta.requestId') : null;
        $retryAfter = $response->header('Retry-After');

        Log::warning('verify_checkout.request_failed', [
            'status' => $response->status(),
            'error_code' => $code,
            'request_id' => $requestId,
            'retryable' => $retryable,
        ]);

        return new VerifyCheckoutException(
            message: $message,
            errorCode: is_string($code) ? $code : null,
            retryable: $retryable,
            requestId: is_string($requestId) ? $requestId : null,
            retryAfter: is_numeric($retryAfter) ? (int) $retryAfter : null,
            status: $response->status(),
        );
    }

    /**
     * @throws VerifyCheckoutException
     */
    public function createDepositWithRetry(array $payload, string $idempotencyKey): array
    {
        try {
            return $this->createDeposit($payload, $idempotencyKey);
        } catch (ConnectionException $exception) {
            Log::warning('verify_checkout.connection_error', [
                'message' => $exception->getMessage(),
            ]);

            try {
                return $this->createDeposit($payload, $idempotencyKey);
            } catch (ConnectionException|RequestException|VerifyCheckoutException $retryException) {
                if ($retryException instanceof VerifyCheckoutException) {
                    throw $retryException;
                }

                throw new VerifyCheckoutException(
                    message: 'Verify Checkout is unreachable.',
                    errorCode: 'internal_server_error',
                    retryable: true,
                    previous: $retryException,
                );
            }
        } catch (VerifyCheckoutException $exception) {
            if ($exception->retryable) {
                if ($exception->retryAfter !== null && $exception->retryAfter > 0) {
                    usleep(min($exception->retryAfter, 5) * 1_000_000);
                }

                return $this->createDeposit($payload, $idempotencyKey);
            }

            throw $exception;
        }
    }
}
