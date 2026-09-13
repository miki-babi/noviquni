<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
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
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public function call(string $method, array $params = []): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('Telegram bot token not configured.', ['method' => $method]);

            return null;
        }

        $response = Http::timeout(15)
            ->post("https://api.telegram.org/bot{$this->token()}/{$method}", $params);

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
