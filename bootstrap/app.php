<?php

use App\Http\Middleware\EnsurePaymentBotSecret;
use App\Http\Middleware\EnsureTelegramMiniAppAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
            'telegram/payment-bot/webhook',
        ]);

        $middleware->alias([
            'telegram.miniapp' => EnsureTelegramMiniAppAuthenticated::class,
            'payment-bot.secret' => EnsurePaymentBotSecret::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('tg') || $request->is('tg/*')) {
                return route('tg.session.create', ['redirect' => $request->fullUrl()]);
            }

            return '/admin/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
