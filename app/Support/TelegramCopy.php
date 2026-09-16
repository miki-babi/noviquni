<?php

namespace App\Support;

use App\Enums\TelegramLocale;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramCopy
{
    public function __construct(public string $locale = TelegramLocale::English->value) {}

    public static function for(User $user): self
    {
        $locale = TelegramLocale::tryFrom((string) ($user->telegram_locale ?? ''))
            ?? TelegramLocale::English;

        return new self($locale->value);
    }

    /**
     * @param  array<string, scalar|null>  $replace
     */
    public function get(string $key, array $replace = []): string
    {
        return (string) __("telegram.{$key}", $replace, $this->locale);
    }

    public function firstName(User $user): string
    {
        $name = trim((string) $user->name);

        if ($name === '') {
            return 'Student';
        }

        return Str::of($name)->before(' ')->toString() ?: $name;
    }
}
