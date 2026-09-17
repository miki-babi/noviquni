<?php

namespace App\Support;

class TelegramLottie
{
    /**
     * Absolute public path for a named Lottie asset, or null if missing.
     */
    public static function path(string $name): ?string
    {
        $filename = config('telegram.lottie.'.$name);

        if (! is_string($filename) || $filename === '') {
            return null;
        }

        $absolute = public_path('lottie/'.$filename);

        if (! is_file($absolute)) {
            return null;
        }

        return $absolute;
    }

    /**
     * Public URL for a named Lottie asset, or null if missing.
     */
    public static function url(string $name): ?string
    {
        $path = self::path($name);

        if ($path === null) {
            return null;
        }

        $filename = basename($path);

        return asset('lottie/'.rawurlencode($filename));
    }

    public static function exists(string $name): bool
    {
        return self::path($name) !== null;
    }
}
