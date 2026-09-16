<?php

namespace App\Services\College;

use Exception;
use Illuminate\Http\Client\RequestException;
use Throwable;

class CollegeApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly mixed $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromRequestException(RequestException $exception): self
    {
        $response = $exception->response;

        return new self(
            message: 'College API request failed: '.($response->json('message') ?? $response->body() ?: $exception->getMessage()),
            status: $response->status(),
            body: $response->json() ?? $response->body(),
            previous: $exception,
        );
    }
}
