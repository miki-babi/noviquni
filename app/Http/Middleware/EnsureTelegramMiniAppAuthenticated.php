<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelegramMiniAppAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        Log::info('Telegram mini-app guest bootstrap', [
            'path' => '/'.$request->path(),
            'host' => $request->getHost(),
            'expects_json' => $request->expectsJson(),
            'has_session_cookie' => $request->hasSession() && $request->session()->isStarted(),
            'user_agent' => substr((string) $request->userAgent(), 0, 180),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Telegram Mini App authentication required.',
            ], 401);
        }

        return response()->view('telegram.mini-app.bootstrap', [
            'intended' => $request->fullUrl(),
        ]);
    }
}
