<?php

namespace App\Services\VerifyCheckout;

use Exception;
use Throwable;

class VerifyCheckoutException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly bool $retryable = false,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
        public readonly int $status = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function customerSafeMessage(): string
    {
        return match ($this->errorCode) {
            'insufficient_credits',
            'order_routing_unavailable',
            'order_initiation_unavailable' => 'Checkout is temporarily unavailable. Please try again shortly.',
            'rate_limited' => 'Too many payment attempts. Please wait a moment and try again.',
            'validation_failed',
            'invalid_request' => 'We could not start checkout. Please try again.',
            default => 'We could not start checkout. Please try again.',
        };
    }
}
