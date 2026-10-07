<?php

namespace App\Enums;

enum YearSlug: string
{
    case Freshman = 'freshman';
    case Second = '2nd';
    case Third = '3rd';
    case FourthAndAbove = '4th_and_above';
    case Graduate = 'graduate';

    public function label(): string
    {
        return match ($this) {
            self::Freshman => 'Freshman',
            self::Second => '2nd',
            self::Third => '3rd',
            self::FourthAndAbove => '4th and above',
            self::Graduate => 'Graduate',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Freshman => 1,
            self::Second => 2,
            self::Third => 3,
            self::FourthAndAbove => 4,
            self::Graduate => 5,
        };
    }
}
