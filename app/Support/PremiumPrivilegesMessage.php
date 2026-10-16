<?php

namespace App\Support;

use App\Models\User;
use App\Services\SettingsService;

class PremiumPrivilegesMessage
{
    public function __construct(public SettingsService $settings) {}

    public function for(?User $user = null, bool $includeUnavailable = false): string
    {
        $copy = $user !== null ? TelegramCopy::for($user) : new TelegramCopy;
        $price = $this->settings->premiumPrice();
        $lines = [
            '<b>'.TelegramHtml::escape($copy->get('premium_landing.title')).'</b>',
            TelegramHtml::escape($copy->get('premium_landing.subtitle')),
            '',
        ];

        foreach ($this->features() as [$titleKey, $bodyKey]) {
            $lines[] = '• <b>'.TelegramHtml::escape($copy->get('premium_landing.'.$titleKey)).'</b> — '
                .TelegramHtml::escape($copy->get('premium_landing.'.$bodyKey));
        }

        $lines[] = '';
        $lines[] = "Price: {$price} ETB";

        if ($includeUnavailable && ($user === null || $this->settings->paymentBotUrl($user) === null)) {
            $lines[] = '';
            $lines[] = TelegramHtml::escape($copy->get('premium_landing.unavailable'));
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function features(): array
    {
        return [
            ['feature_questions_title', 'feature_questions_body'],
            ['feature_quizzes_title', 'feature_quizzes_body'],
            ['feature_short_title', 'feature_short_body'],
            ['feature_exams_title', 'feature_exams_body'],
            ['feature_checked_title', 'feature_checked_body'],
            ['feature_fresh_title', 'feature_fresh_body'],
        ];
    }
}
