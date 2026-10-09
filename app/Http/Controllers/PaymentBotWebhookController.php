<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\PaymentBotHandler;
use App\Services\TelegramBotApi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentBotWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentBotHandler $handler): Response
    {
        $handler->handle($request->all());

        return response('ok');
    }

    public function status(Request $request): View|JsonResponse
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $payload = $this->statusPayload();

        if ($request->expectsJson()) {
            $status = match (true) {
                ! $payload['configured'] => 503,
                $payload['webhook'] === null && $payload['message'] !== null => 502,
                default => 200,
            };

            return response()->json($payload, $status);
        }

        return view('telegram.webhook-status', array_merge($payload, $this->viewMeta()));
    }

    public function set(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(auth()->user()?->role === UserRole::Admin, 403);

        $expectedUrl = route('telegram.payment-bot.webhook', absolute: true);
        $api = $this->api();

        if (! $api->isConfigured()) {
            $message = 'TELEGRAM_PAYMENT_BOT_TOKEN is not set.';

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 503)
                : back()->with('error', $message);
        }

        $result = $api->setWebhook($expectedUrl);

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
                ->route('telegram.payment-bot.webhook-status')
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
    protected function statusPayload(): array
    {
        $expectedUrl = route('telegram.payment-bot.webhook', absolute: true);
        $api = $this->api();

        if (! $api->isConfigured()) {
            return [
                'ok' => false,
                'configured' => false,
                'message' => 'TELEGRAM_PAYMENT_BOT_TOKEN is not set.',
                'expected_webhook_url' => $expectedUrl,
                'matches_expected_url' => false,
                'webhook_is_set' => false,
                'can_set_webhook' => false,
                'webhook' => null,
            ];
        }

        $info = $api->getWebhookInfo();

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

    /**
     * @return array{title: string, subtitle: string, statusRoute: string, setRoute: string, otherBotLabel: string, otherBotRoute: string}
     */
    protected function viewMeta(): array
    {
        return [
            'title' => 'Payment bot webhook',
            'subtitle' => 'Check registration status and set the payment bot webhook to this app. File admins must /start the payment bot once before they can receive screenshot reviews.',
            'statusRoute' => 'telegram.payment-bot.webhook-status',
            'setRoute' => 'telegram.payment-bot.webhook-set',
            'otherBotLabel' => 'Main bot webhook',
            'otherBotRoute' => 'telegram.webhook-status',
        ];
    }

    protected function api(): TelegramBotApi
    {
        $token = config('services.payment_bot.token');

        return new TelegramBotApi(
            filled($token) ? (string) $token : null,
            'TELEGRAM_PAYMENT_BOT_TOKEN is not set.',
        );
    }
}
