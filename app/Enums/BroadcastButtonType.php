<?php

namespace App\Enums;

enum BroadcastButtonType: string
{
    case Command = 'command';
    case Text = 'text';
    case Url = 'url';
    case MiniApp = 'mini_app';

    public function label(): string
    {
        return match ($this) {
            self::Command => 'Bot command / callback',
            self::Text => 'Text reply',
            self::Url => 'Web URL',
            self::MiniApp => 'Telegram Mini App',
        };
    }
}
