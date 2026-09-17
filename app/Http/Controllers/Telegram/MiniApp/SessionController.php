<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramWebAppAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use UnexpectedValueException;

class SessionController extends Controller
{
    public function create(Request $request): View
    {
        return view('telegram.mini-app.bootstrap', [
            'intended' => $request->string('redirect')->toString() ?: route('tg.home'),
        ]);
    }

    public function store(Request $request, TelegramWebAppAuth $auth): RedirectResponse
    {
        $validated = $request->validate([
            'init_data' => ['required', 'string'],
            'redirect' => ['nullable', 'string'],
        ]);

        try {
            $auth->authenticate($validated['init_data']);
        } catch (UnexpectedValueException $exception) {
            return redirect()
                ->route('tg.session.create', ['redirect' => $validated['redirect'] ?? route('tg.home')])
                ->with('error', $exception->getMessage());
        }

        $redirect = $validated['redirect'] ?? route('tg.home');

        if (! $this->isSafeMiniAppRedirect($redirect)) {
            $redirect = route('tg.home');
        }

        return redirect()->to($redirect);
    }

    protected function isSafeMiniAppRedirect(string $redirect): bool
    {
        $path = parse_url($redirect, PHP_URL_PATH);

        return is_string($path) && ($path === '/tg' || str_starts_with($path, '/tg/'));
    }
}
