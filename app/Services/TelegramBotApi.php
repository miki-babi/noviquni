<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotApi
{
    public function __construct(
        public readonly ?string $token,
        public readonly string $missingTokenMessage = 'Bot token is not set.',
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWebhookInfo(): ?array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * @param  list<string>  $allowedUpdates
     * @return array{ok: bool, description?: string, result?: array<string, mixed>|bool|null, error?: array<string, mixed>|null}
     */
    public function setWebhook(string $url, array $allowedUpdates = ['message', 'callback_query']): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'description' => $this->missingTokenMessage,
                'result' => null,
                'error' => null,
            ];
        }

        $response = Http::timeout(15)->post(
            "https://api.telegram.org/bot{$this->token}/setWebhook",
            [
                'url' => $url,
                'allowed_updates' => $allowedUpdates,
                'drop_pending_updates' => false,
            ],
        );

        $body = $response->json() ?? [];

        if (! $response->successful() || ! ($body['ok'] ?? false)) {
            Log::error('Telegram setWebhook error', ['body' => $body]);

            return [
                'ok' => false,
                'description' => (string) ($body['description'] ?? 'Failed to set webhook.'),
                'result' => null,
                'error' => is_array($body) ? $body : null,
            ];
        }

        return [
            'ok' => true,
            'description' => (string) ($body['description'] ?? 'Webhook was set.'),
            'result' => $body['result'] ?? true,
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function call(string $method, array $params = []): ?array
    {
        $outcome = $this->execute($method, $params);

        if (! $outcome['ok']) {
            return null;
        }

        return $outcome['result'];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, result: array<string, mixed>|null, body: array<string, mixed>|null}
     */
    public function execute(string $method, array $params = []): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token not configured.', ['method' => $method]);

            return [
                'ok' => false,
                'result' => null,
                'body' => null,
            ];
        }

        $url = "https://api.telegram.org/bot{$this->token}/{$method}";

        $response = $params === []
            ? Http::timeout(15)->get($url)
            : Http::timeout(15)->post($url, $params);

        $body = $response->json();

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            Log::error('Telegram API error', [
                'method' => $method,
                'body' => $body,
            ]);

            return [
                'ok' => false,
                'result' => null,
                'body' => is_array($body) ? $body : null,
            ];
        }

        $result = $response->json('result');

        if ($result === true) {
            return [
                'ok' => true,
                'result' => [],
                'body' => is_array($body) ? $body : null,
            ];
        }

        return [
            'ok' => true,
            'result' => is_array($result) ? $result : null,
            'body' => is_array($body) ? $body : null,
        ];
    }
}
