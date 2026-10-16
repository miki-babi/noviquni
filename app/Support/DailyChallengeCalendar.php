<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DailyChallengeCalendar
{
    public const string Timezone = 'Africa/Nairobi';

    public static function now(?CarbonInterface $at = null): Carbon
    {
        return ($at ?? now())->copy()->timezone(self::Timezone);
    }

    public static function today(?CarbonInterface $at = null): Carbon
    {
        return self::now($at)->startOfDay();
    }

    public static function todayDateString(?CarbonInterface $at = null): string
    {
        return self::today($at)->toDateString();
    }
}
