<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const PREMIUM_PRICE = 'premium_price';

    public const REQUIRED_REFERRALS = 'required_referrals';

    public const FREE_REFERRAL_REWARD = 'free_referral_reward';

    public const PREMIUM_REFERRAL_REWARD = 'premium_referral_reward';

    public const PREMIUM_DURATION_DAYS = 'premium_duration_days';

    public const PAYMENT_INSTRUCTIONS = 'payment_instructions';

    public const TELEGRAM_START_IMAGE = 'telegram_start_image';

    public const TELEGRAM_START_CAPTION = 'telegram_start_caption';

    public const TELEGRAM_START_BUTTONS = 'telegram_start_buttons';

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return [
            self::PREMIUM_PRICE => '30',
            self::REQUIRED_REFERRALS => '5',
            self::FREE_REFERRAL_REWARD => '2',
            self::PREMIUM_REFERRAL_REWARD => '10',
            self::PREMIUM_DURATION_DAYS => '30',
            self::PAYMENT_INSTRUCTIONS => "Send {amount} ETB to the account provided by support.\nUse payment reference: {reference}",
            self::TELEGRAM_START_IMAGE => '',
            self::TELEGRAM_START_CAPTION => '',
            self::TELEGRAM_START_BUTTONS => '[]',
        ];
    }

    public function get(string $key, ?string $default = null): string
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default): string {
            $value = Setting::query()->where('key', $key)->value('value');

            if ($value !== null) {
                return (string) $value;
            }

            return $default ?? ($this->defaults()[$key] ?? '');
        });
    }

    public function set(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("settings.{$key}");
    }

    public function premiumPrice(): float
    {
        return (float) $this->get(self::PREMIUM_PRICE, '30');
    }

    public function requiredReferrals(): int
    {
        return (int) $this->get(self::REQUIRED_REFERRALS, '5');
    }

    public function freeReferralReward(): float
    {
        return (float) $this->get(self::FREE_REFERRAL_REWARD, '2');
    }

    public function premiumReferralReward(): float
    {
        return (float) $this->get(self::PREMIUM_REFERRAL_REWARD, '10');
    }

    public function premiumDurationDays(): int
    {
        return (int) $this->get(self::PREMIUM_DURATION_DAYS, '30');
    }

    public function paymentInstructions(): string
    {
        return $this->get(self::PAYMENT_INSTRUCTIONS);
    }

    public function telegramStartImage(): ?string
    {
        $path = trim($this->get(self::TELEGRAM_START_IMAGE, ''));

        return $path !== '' ? $path : null;
    }

    public function telegramStartCaption(): string
    {
        return $this->get(self::TELEGRAM_START_CAPTION, '');
    }

    /**
     * @return array<int, array{label?: string, type?: string, command?: string, url?: string, style?: string|null}>
     */
    public function telegramStartButtons(): array
    {
        $decoded = json_decode($this->get(self::TELEGRAM_START_BUTTONS, '[]'), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    public function hasCustomTelegramStartMessage(): bool
    {
        return $this->telegramStartImage() !== null
            || filled(trim($this->telegramStartCaption()))
            || $this->telegramStartButtons() !== [];
    }

    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("settings.{$key}");
        }
    }
}
