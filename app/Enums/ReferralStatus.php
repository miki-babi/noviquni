<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Completed = 'completed';
}
