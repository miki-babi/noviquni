<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.telegram.webhook_secret');

        if (filled($secret)) {
            $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

            if (! hash_equals((string) $secret, (string) $header)) {
                abort(403, 'Invalid Telegram webhook secret.');
            }
        }

        return $next($request);
    }
}
