<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\TelegramHtml;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const PREMIUM_PRICE = 'premium_price';

    public const REQUIRED_REFERRALS = 'required_referrals';

    public const POINTS_PER_REFERRAL = 'points_per_referral';

    public const PREMIUM_DURATION_DAYS = 'premium_duration_days';

    public const PAYMENT_INSTRUCTIONS = 'payment_instructions';

    public const PAYMENT_BOT_USERNAME = 'payment_bot_username';

    public const PAYMENT_BOT_WELCOME = 'payment_bot_welcome';

    public const PAYMENT_BOT_WHAT_YOU_GET = 'payment_bot_what_you_get';

    public const PAYMENT_BOT_OUR_STORY = 'payment_bot_our_story';

    public const PAYMENT_BOT_OUR_MISSION = 'payment_bot_our_mission';

    public const PAYMENT_BOT_CONTACT_US = 'payment_bot_contact_us';

    public const PAYMENT_BOT_REGISTER_PROMPT = 'payment_bot_register_prompt';

    public const TELEGRAM_START_IMAGE = 'telegram_start_image';

    public const TELEGRAM_START_CAPTION = 'telegram_start_caption';

    public const TELEGRAM_START_BUTTONS = 'telegram_start_buttons';

    public const COHORT_URGENCY_COPY = 'cohort_urgency_copy';

    public const PREMIUM_PITCH_TITLE = 'premium_pitch_title';

    public const PREMIUM_PITCH_BODY = 'premium_pitch_body';

    public const OPPORTUNITY_GUIDANCE_USERNAME = 'opportunity_guidance_username';

    public const OPPORTUNITY_GUIDANCE_OPENING_MESSAGE = 'opportunity_guidance_opening_message';

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        return [
            self::PREMIUM_PRICE => '30',
            self::REQUIRED_REFERRALS => '3',
            self::POINTS_PER_REFERRAL => '1',
            self::PREMIUM_DURATION_DAYS => '30',
            self::PAYMENT_INSTRUCTIONS => "Send {amount} ETB to the account provided by support.\nUse payment reference: {reference}",
            self::PAYMENT_BOT_USERNAME => '',
            self::PAYMENT_BOT_WELCOME => "Welcome to NoviqUni Premium payments.\n\nUse the buttons below to learn more, or tap Register / Pay when you are ready.",
            self::PAYMENT_BOT_WHAT_YOU_GET => "Opportunity and internship links stay free for everyone.\n\nPremium gives you personal guidance on applications, plus full access to premium modules, notes, worksheets, quizzes, flashcards, and exams.\n\nPay once, or unlock Premium free by inviting friends.",
            self::PAYMENT_BOT_OUR_STORY => 'We started NoviqUni to help Ethiopian university students find clearer paths through courses, opportunities, and guidance.',
            self::PAYMENT_BOT_OUR_MISSION => 'Our mission is to make high-quality learning support and opportunity guidance accessible to every student who needs it.',
            self::PAYMENT_BOT_CONTACT_US => 'Need help? Message support through the main NoviqUni bot, or reply here and our team will follow up.',
            self::PAYMENT_BOT_REGISTER_PROMPT => 'When you have paid, send a photo of the transfer receipt here.',
            self::TELEGRAM_START_IMAGE => '',
            self::TELEGRAM_START_CAPTION => '',
            self::TELEGRAM_START_BUTTONS => '[]',
            self::COHORT_URGENCY_COPY => 'Seasonal cohort: unlock full resources this week — 30 ETB or invite 3 friends.',
            self::PREMIUM_PITCH_TITLE => 'Premium unlocks 1:1 guidance',
            self::PREMIUM_PITCH_BODY => "Opportunity and internship links stay free for everyone.\n\nPremium gives you personal guidance on applications, plus full access to premium modules, notes, worksheets, quizzes, flashcards, and exams.\n\nPay once, or unlock Premium free by inviting friends.",
            self::OPPORTUNITY_GUIDANCE_USERNAME => '',
            self::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE => "Hi, I'd like guidance on {title} (partner: {partner}).\n\nFrom: {student}",
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
        return (int) $this->get(self::REQUIRED_REFERRALS, '3');
    }

    public function pointsPerReferral(): int
    {
        return max(1, (int) $this->get(self::POINTS_PER_REFERRAL, '1'));
    }

    public function premiumDurationDays(): int
    {
        return (int) $this->get(self::PREMIUM_DURATION_DAYS, '30');
    }

    public function paymentInstructions(): string
    {
        return $this->get(self::PAYMENT_INSTRUCTIONS);
    }

    public function paymentBotUsername(): ?string
    {
        $fromSettings = ltrim(trim($this->get(self::PAYMENT_BOT_USERNAME, '')), '@');

        if ($fromSettings !== '') {
            return $fromSettings;
        }

        $fromConfig = ltrim(trim((string) config('services.payment_bot.username', '')), '@');

        return $fromConfig !== '' ? $fromConfig : null;
    }

    public function paymentBotUrl(?User $user = null): ?string
    {
        $username = $this->paymentBotUsername();

        if ($username === null) {
            return null;
        }

        $url = 'https://t.me/'.$username;

        if ($user !== null) {
            $url .= '?start=u'.$user->id;
        }

        return $url;
    }

    public function paymentBotWelcome(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_WELCOME);
    }

    public function paymentBotWhatYouGet(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_WHAT_YOU_GET);
    }

    public function paymentBotOurStory(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_OUR_STORY);
    }

    public function paymentBotOurMission(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_OUR_MISSION);
    }

    public function paymentBotContactUs(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_CONTACT_US);
    }

    public function paymentBotRegisterPrompt(): string
    {
        return $this->settingOrDefault(self::PAYMENT_BOT_REGISTER_PROMPT);
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
            || ! TelegramHtml::isBlank($this->telegramStartCaption())
            || $this->telegramStartButtons() !== [];
    }

    public function cohortUrgencyCopy(): string
    {
        return $this->get(self::COHORT_URGENCY_COPY);
    }

    public function premiumPitchTitle(): string
    {
        $title = trim($this->get(
            self::PREMIUM_PITCH_TITLE,
            $this->defaults()[self::PREMIUM_PITCH_TITLE],
        ));

        return $title !== ''
            ? $title
            : $this->defaults()[self::PREMIUM_PITCH_TITLE];
    }

    public function premiumPitchBody(): string
    {
        $body = trim($this->get(
            self::PREMIUM_PITCH_BODY,
            $this->defaults()[self::PREMIUM_PITCH_BODY],
        ));

        return $body !== ''
            ? $body
            : $this->defaults()[self::PREMIUM_PITCH_BODY];
    }

    public function opportunityGuidanceUsername(): ?string
    {
        $username = ltrim(trim($this->get(self::OPPORTUNITY_GUIDANCE_USERNAME, '')), '@');

        return $username !== '' ? $username : null;
    }

    public function opportunityGuidanceOpeningMessage(): string
    {
        $message = trim($this->get(
            self::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE,
            $this->defaults()[self::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE],
        ));

        return $message !== ''
            ? $message
            : $this->defaults()[self::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE];
    }

    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("settings.{$key}");
        }
    }

    protected function settingOrDefault(string $key): string
    {
        $value = trim($this->get($key, $this->defaults()[$key] ?? ''));

        return $value !== ''
            ? $value
            : ($this->defaults()[$key] ?? '');
    }
}
