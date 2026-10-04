<?php

namespace App\Enums;

enum SubscriptionSource: string
{
    case Payment = 'payment';
    case Referral = 'referral';
    case Challenge = 'challenge';
    case Admin = 'admin';
}
