<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaymentBotSecret
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.payment_bot.api_secret');

        if ($configured === '') {
            abort(503, 'Payment bot API is not configured.');
        }

        $provided = $this->providedSecret($request);

        if ($provided === '' || ! hash_equals($configured, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }

    protected function providedSecret(Request $request): string
    {
        $header = (string) $request->header('X-Payment-Bot-Secret', '');

        if ($header !== '') {
            return $header;
        }

        $authorization = (string) $request->header('Authorization', '');

        if (str_starts_with($authorization, 'Bearer ')) {
            return trim(substr($authorization, 7));
        }

        return '';
    }
}
