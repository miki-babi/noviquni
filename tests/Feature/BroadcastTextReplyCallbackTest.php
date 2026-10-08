<?php

use App\Enums\OnboardingStep;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.verify_checkout.api_key' => 'vchk_test_key',
        'services.verify_checkout.api_version' => '2026-06-01',
        'app.url' => 'https://noviquni.test',
    ]);
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
        'checkoutapi.verify.et/v1/deposits' => Http::response([
            'data' => [
                'id' => 'dep_premium_menu_1',
                'merchant_order_id' => 'order_1',
                'merchant_customer_id' => 'user_1',
                'status' => 'awaiting_transfer',
                'amount' => '30.00',
                'currency' => 'ETB',
                'checkout_url' => 'https://checkout.verify.et/c/premium_menu_token',
                'support_reference' => 'VC-3001',
                'expires_at' => now()->addHour()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
            ],
            'meta' => ['requestId' => 'req_premium_menu', 'apiVersion' => '2026-06-01'],
        ], 201),
    ]);
});

it('handles text reply inline callbacks like typed menu text', function () {
    User::factory()->student()->create([
        'telegram_id' => '555901',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9011,
        'callback_query' => [
            'id' => 'callback-9011',
            'data' => 'reply:⭐ Premium',
            'from' => [
                'id' => 555901,
                'first_name' => 'Test',
                'username' => 'tester',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => 555901],
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');
        $payButton = data_get($data, 'reply_markup.inline_keyboard.0.0', []);
        $referButton = data_get($data, 'reply_markup.inline_keyboard.1.0', []);

        return str_contains($text, 'Premium unlocks 1:1 guidance')
            && ($payButton['url'] ?? null) === 'https://checkout.verify.et/c/premium_menu_token'
            && ($payButton['text'] ?? null) === 'Become Premium'
            && ! array_key_exists('web_app', $payButton)
            && str_contains((string) data_get($referButton, 'web_app.url', ''), '/tg/profile')
            && ! str_contains(json_encode($data['reply_markup'] ?? []), 'tg.premium');
    });

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return str_contains($text, 'invalid');
    });
});
