<?php

namespace App\Enums;

enum UserEventName: string
{
    case Start = 'start';
    case OnboardingDone = 'onboarding_done';
    case ResourceOpen = 'resource_open';
    case QuizDone = 'quiz_done';
    case PremiumView = 'premium_view';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentApproved = 'payment_approved';
    case PaymentReverted = 'payment_reverted';
    case ReferralLinkShared = 'referral_link_shared';
    case ReferralJoined = 'referral_joined';
    case Blocked = 'blocked';
}
