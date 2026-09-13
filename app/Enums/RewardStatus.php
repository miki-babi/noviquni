<?php

namespace App\Enums;

enum RewardStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
