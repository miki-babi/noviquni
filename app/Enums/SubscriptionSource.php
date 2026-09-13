<?php

namespace App\Enums;

enum SubscriptionSource: string
{
    case Payment = 'payment';
    case Referral = 'referral';
    case Admin = 'admin';
}
