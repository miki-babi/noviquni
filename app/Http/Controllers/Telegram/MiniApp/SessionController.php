<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramWebAppAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use UnexpectedValueException;

class SessionController extends Controller
{
    public function create(Request $request): View
    {
        return view('telegram.mini-app.bootstrap', [
            'intended' => $request->string('redirect')->toString() ?: route('tg.home', [], false),
        ]);
    }

    public function store(Request $request, TelegramWebAppAuth $auth): RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
            'redirect' => ['nullable', 'string'],
        ]);

        $meta = $auth->safeInitDataMeta($validated['init_data']);

        Log::info('Telegram mini-app session.store attempt', [
            ...$meta,
            'redirect_path' => parse_url((string) ($validated['redirect'] ?? ''), PHP_URL_PATH),
            'host' => $request->getHost(),
        ]);

        try {
            $user = $auth->authenticate($validated['init_data']);
        } catch (UnexpectedValueException $exception) {
            Log::warning('Telegram mini-app session.store failed', [
                ...$meta,
                'reason' => $exception->getMessage(),
                'host' => $request->getHost(),
            ]);

            return redirect()
                ->to(route('tg.session.create', [
                    'redirect' => $validated['redirect'] ?? route('tg.home', [], false),
                ], false))
                ->with('error', $exception->getMessage());
        }

        $redirect = $validated['redirect'] ?? route('tg.home', [], false);
        $redirectWasRewritten = false;

        if (! $this->isSafeMiniAppRedirect($redirect)) {
            $redirectWasRewritten = true;
            $redirect = route('tg.home', [], false);
        }

        Log::info('Telegram mini-app session.store succeeded', [
            'user_id' => $user->id,
            'telegram_id' => $user->telegram_id,
            'redirect_path' => parse_url($redirect, PHP_URL_PATH),
            'redirect_rewritten' => $redirectWasRewritten,
            'host' => $request->getHost(),
        ]);

        return redirect()->to($redirect);
    }

    public function diagnose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'in:bootstrap_served,init_data_missing,init_data_present,auth_error'],
            'has_telegram' => ['nullable', 'boolean'],
            'has_init_data' => ['nullable', 'boolean'],
            'init_data_length' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'tg_platform' => ['nullable', 'string', 'max:64'],
            'tg_version' => ['nullable', 'string', 'max:32'],
            'path' => ['nullable', 'string', 'max:255'],
            'waited_ms' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        Log::info('Telegram mini-app client diagnose', [
            ...$validated,
            'host' => $request->getHost(),
            'user_agent' => substr((string) $request->userAgent(), 0, 180),
        ]);

        return response()->json(['ok' => true]);
    }

    protected function isSafeMiniAppRedirect(string $redirect): bool
    {
        $path = parse_url($redirect, PHP_URL_PATH);

        return is_string($path) && ($path === '/tg' || str_starts_with($path, '/tg/'));
    }
}
