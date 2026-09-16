<?php

namespace App\Enums;

enum TelegramButtonStyle: string
{
    case Primary = 'primary';
    case Success = 'success';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary (blue)',
            self::Success => 'Success (green)',
            self::Danger => 'Danger (red)',
        };
    }
}
