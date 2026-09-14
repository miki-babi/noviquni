<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Services\TelegramBotHandler;
use App\Services\TelegramService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotHandler $handler): Response
    {
        $update = $request->all();

        if (config('queue.default') === 'sync') {
            $handler->handle($update);
        } else {
            ProcessTelegramUpdateJob::dispatch($update);
        }

        return response('ok');
    }

    public function status(Request $request, TelegramService $telegram): View|JsonResponse
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $payload = $this->statusPayload($telegram);

        if ($request->expectsJson()) {
            $status = match (true) {
                ! $payload['configured'] => 503,
                $payload['webhook'] === null && $payload['message'] !== null => 502,
                default => 200,
            };

            return response()->json($payload, $status);
        }

        return view('telegram.webhook-status', $payload);
    }

    public function set(Request $request, TelegramService $telegram): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $expectedUrl = route('telegram.webhook', absolute: true);

        if (! $telegram->isConfigured()) {
            $message = 'TELEGRAM_BOT_TOKEN is not set.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 503)
                : back()->with('error', $message);
        }

        $result = $telegram->setWebhook($expectedUrl);

        if (! $result['ok']) {
            $message = $result['description'];

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message, 'error' => $result['error']], 502)
                : back()->with('error', $message);
        }

        $message = 'Webhook set to '.$expectedUrl;

        return $request->expectsJson()
            ? response()->json([
                'ok' => true,
                'message' => $message,
                'expected_webhook_url' => $expectedUrl,
            ])
            : redirect()
                ->route('telegram.webhook-status')
                ->with('success', $message);
    }

    /**
     * @return array{
     *     ok: bool,
     *     configured: bool,
     *     message: ?string,
     *     expected_webhook_url: string,
     *     matches_expected_url: bool,
     *     webhook_is_set: bool,
     *     can_set_webhook: bool,
     *     webhook: ?array<string, mixed>
     * }
     */
    protected function statusPayload(TelegramService $telegram): array
    {
        $expectedUrl = route('telegram.webhook', absolute: true);

        if (! $telegram->isConfigured()) {
            return [
                'ok' => false,
                'configured' => false,
                'message' => 'TELEGRAM_BOT_TOKEN is not set.',
                'expected_webhook_url' => $expectedUrl,
                'matches_expected_url' => false,
                'webhook_is_set' => false,
                'can_set_webhook' => false,
                'webhook' => null,
            ];
        }

        $info = $telegram->getWebhookInfo();

        if ($info === null) {
            return [
                'ok' => false,
                'configured' => true,
                'message' => 'Could not fetch webhook info from Telegram.',
                'expected_webhook_url' => $expectedUrl,
                'matches_expected_url' => false,
                'webhook_is_set' => false,
                'can_set_webhook' => true,
                'webhook' => null,
            ];
        }

        $currentUrl = (string) ($info['url'] ?? '');
        $webhookIsSet = filled($currentUrl);
        $matches = $currentUrl === $expectedUrl;

        return [
            'ok' => true,
            'configured' => true,
            'message' => null,
            'expected_webhook_url' => $expectedUrl,
            'matches_expected_url' => $matches,
            'webhook_is_set' => $webhookIsSet,
            'can_set_webhook' => ! $matches,
            'webhook' => $info,
        ];
    }
}
