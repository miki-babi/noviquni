<?php

namespace App\Enums;

enum GuidanceRequestStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
}
