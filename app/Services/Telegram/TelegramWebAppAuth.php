<?php

namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use UnexpectedValueException;

class TelegramWebAppAuth
{
    public function __construct(public int $maxAgeSeconds = 86400) {}

    /**
     * Safe metadata for logs — never includes hash or raw initData.
     *
     * @return array{
     *     init_data_length: int,
     *     has_hash: bool,
     *     has_user: bool,
     *     auth_date: int|null,
     *     auth_age_seconds: int|null,
     *     telegram_user_id: string|null
     * }
     */
    public function safeInitDataMeta(string $initData): array
    {
        $params = [];
        parse_str($initData, $params);

        $authDate = isset($params['auth_date']) ? (int) $params['auth_date'] : null;
        $userJson = (string) ($params['user'] ?? '');
        $user = json_decode($userJson, true);
        $telegramUserId = is_array($user) && isset($user['id']) ? (string) $user['id'] : null;

        return [
            'init_data_length' => strlen($initData),
            'has_hash' => filled($params['hash'] ?? null),
            'has_user' => filled($userJson),
            'auth_date' => $authDate > 0 ? $authDate : null,
            'auth_age_seconds' => $authDate > 0 ? abs(now()->timestamp - $authDate) : null,
            'telegram_user_id' => $telegramUserId,
        ];
    }

    /**
     * @return array{user: array<string, mixed>, auth_date: int, raw: array<string, string>}
     */
    public function validate(string $initData, ?string $botToken = null): array
    {
        $botToken ??= (string) config('services.telegram.bot_token');

        if ($botToken === '') {
            throw new UnexpectedValueException('Telegram bot token is not configured.');
        }

        $params = [];
        parse_str($initData, $params);

        if (! is_array($params) || blank($params['hash'] ?? null)) {
            throw new UnexpectedValueException('Invalid Telegram initData.');
        }

        $hash = (string) $params['hash'];
        unset($params['hash']);

        ksort($params);

        $dataCheckString = collect($params)
            ->map(fn (mixed $value, string $key): string => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

        if (! hash_equals($calculatedHash, $hash)) {
            throw new UnexpectedValueException('Telegram initData signature is invalid.');
        }

        $authDate = (int) ($params['auth_date'] ?? 0);

        if ($authDate <= 0 || abs(now()->timestamp - $authDate) > $this->maxAgeSeconds) {
            throw new UnexpectedValueException('Telegram initData has expired.');
        }

        $userJson = (string) ($params['user'] ?? '');
        $user = json_decode($userJson, true);

        if (! is_array($user) || blank($user['id'] ?? null)) {
            throw new UnexpectedValueException('Telegram initData user is missing.');
        }

        return [
            'user' => $user,
            'auth_date' => $authDate,
            'raw' => array_map(fn (mixed $value): string => (string) $value, $params),
        ];
    }

    public function authenticate(string $initData, ?string $botToken = null): User
    {
        $payload = $this->validate($initData, $botToken);
        $telegramId = (string) $payload['user']['id'];

        $user = User::query()
            ->where('telegram_id', $telegramId)
            ->first();

        if ($user === null) {
            throw new UnexpectedValueException('Open the bot with /start first.');
        }

        if (! $user->is_active) {
            throw new UnexpectedValueException('Your account is disabled. Contact support.');
        }

        Auth::login($user);

        return $user;
    }
}
