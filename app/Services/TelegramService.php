<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public const string InlineMessageCacheKey = 'telegram.onboarding_message.';

    public const string OnboardingPromptRateLimitKey = 'telegram.onboarding_prompt.';

    public function token(): ?string
    {
        return config('services.telegram.bot_token');
    }

    public function isConfigured(): bool
    {
        return filled($this->token());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendMessage(int|string $chatId, string $text, array $payload = []): ?array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendDocument(int|string $chatId, string $fileUrlOrId, string $caption = ''): ?array
    {
        return $this->call('sendDocument', [
            'chat_id' => $chatId,
            'document' => $fileUrlOrId,
            'caption' => $caption,
        ]);
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function mainKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📚 My Courses'], ['text' => '📖 Resources']],
                [['text' => '⭐ Premium'], ['text' => '👥 Refer & Earn']],
                [['text' => '🔔 Notifications'], ['text' => '👤 My Profile']],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * @param  array<int, array<int, array<string, string>>>  $rows
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}
     */
    public function inlineKeyboard(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $payload = []): ?array
    {
        return $this->call('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $payload));
    }

    /**
     * Send a new message or edit an existing one when messageId is present.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function replyOrEdit(int|string $chatId, string $text, array $payload = [], ?int $messageId = null): ?array
    {
        if ($messageId !== null) {
            return $this->editMessageText($chatId, $messageId, $text, $payload);
        }

        return $this->sendMessage($chatId, $text, $payload);
    }

    public function rememberInlineMessage(int $userId, ?array $result, ?int $messageId = null): void
    {
        $resolvedMessageId = $messageId ?? (isset($result['message_id']) ? (int) $result['message_id'] : null);

        if ($resolvedMessageId === null) {
            return;
        }

        cache()->put(self::InlineMessageCacheKey.$userId, $resolvedMessageId, now()->addHour());
    }

    public function cachedInlineMessageId(int $userId): ?int
    {
        $messageId = cache()->get(self::InlineMessageCacheKey.$userId);

        return is_int($messageId) ? $messageId : null;
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): ?array
    {
        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }

        return $this->call('answerCallbackQuery', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWebhookInfo(): ?array
    {
        return $this->call('getWebhookInfo');
    }

    /**
     * @return array{ok: bool, description?: string, result?: array<string, mixed>|null, error?: array<string, mixed>|null}
     */
    public function setWebhook(string $url): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'description' => 'TELEGRAM_BOT_TOKEN is not set.',
                'result' => null,
                'error' => null,
            ];
        }

        $response = Http::timeout(15)->post(
            "https://api.telegram.org/bot{$this->token()}/setWebhook",
            [
                'url' => $url,
                'allowed_updates' => [
                    'message',
                    'callback_query',
                ],
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
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token not configured.', ['method' => $method]);

            return null;
        }

        $url = "https://api.telegram.org/bot{$this->token()}/{$method}";

        $response = $params === []
            ? Http::timeout(15)->get($url)
            : Http::timeout(15)->post($url, $params);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            Log::error('Telegram API error', [
                'method' => $method,
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json('result');
    }

    public function findOrCreateStudent(int|string $telegramId, string $name, ?string $username = null): User
    {
        $user = User::query()->where('telegram_id', (string) $telegramId)->first();

        if ($user !== null) {
            $user->update([
                'name' => $name,
                'telegram_username' => $username,
            ]);

            return $user->refresh();
        }

        return User::query()->create([
            'name' => $name,
            'telegram_id' => (string) $telegramId,
            'telegram_username' => $username,
            'role' => UserRole::Student,
            'onboarding_step' => OnboardingStep::Start,
            'is_active' => true,
        ]);
    }
}
