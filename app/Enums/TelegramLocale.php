<?php

namespace App\Enums;

enum TelegramLocale: string
{
    case English = 'en';
    case Amharic = 'am';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Amharic => 'አማርኛ',
        };
    }
}
