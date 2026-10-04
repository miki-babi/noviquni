<?php

namespace App\Enums;

enum ChallengeRewardType: string
{
    case PremiumDays = 'premium_days';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::PremiumDays => 'Premium days',
            self::Message => 'Prize message',
        };
    }
}
