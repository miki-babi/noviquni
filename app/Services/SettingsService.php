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

    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("settings.{$key}");
        }
    }
}
