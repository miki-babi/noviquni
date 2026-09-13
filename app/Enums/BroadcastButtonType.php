<?php

namespace App\Enums;

enum BroadcastButtonType: string
{
    case Command = 'command';
    case Url = 'url';
    case MiniApp = 'mini_app';

    public function label(): string
    {
        return match ($this) {
            self::Command => 'Bot command / callback',
            self::Url => 'Web URL',
            self::MiniApp => 'Telegram Mini App',
        };
    }
}
