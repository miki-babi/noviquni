<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureImportToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.import.api_token');

        if ($configured === '') {
            abort(503, 'Import API is not configured.');
        }

        $provided = $this->providedToken($request);

        if ($provided === '' || ! hash_equals($configured, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }

    protected function providedToken(Request $request): string
    {
        $header = (string) $request->header('X-Import-Token', '');

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
