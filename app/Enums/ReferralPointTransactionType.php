<?php

namespace App\Enums;

enum ReferralPointTransactionType: string
{
    case ReferralEarned = 'referral_earned';
    case ChallengeSpent = 'challenge_spent';
    case AdminAdjustment = 'admin_adjustment';
}
