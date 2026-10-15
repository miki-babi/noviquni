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
        'services.payment_bot.username' => 'noviquni_pay_bot',
        'app.url' => 'https://noviquni.test',
    ]);
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('handles text reply inline callbacks like typed menu text', function () {
    $user = User::factory()->student()->create([
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

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');
        $payButton = data_get($data, 'reply_markup.inline_keyboard.0.0', []);
        $keyboard = data_get($data, 'reply_markup.inline_keyboard', []);

        return str_contains($text, 'Walk into the GAT ready.')
            && ! str_contains($text, 'Referrals:')
            && ($payButton['url'] ?? null) === 'https://t.me/noviquni_pay_bot?start=u'.$user->id
            && ($payButton['text'] ?? null) === 'Become Premium'
            && ! array_key_exists('web_app', $payButton)
            && count($keyboard) === 1
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
