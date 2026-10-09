<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('requires authentication for payment bot webhook status', function () {
    $this->getJson('/telegram/payment-bot/webhook-status')
        ->assertUnauthorized();
});

it('forbids students from payment bot webhook status', function () {
    $student = User::factory()->student()->create([
        'email' => 'student@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($student)
        ->getJson('/telegram/payment-bot/webhook-status')
        ->assertForbidden();
});

it('returns payment bot webhook status for admins', function () {
    config(['services.payment_bot.token' => 'payment-test-token']);

    Http::fake([
        'api.telegram.org/*/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => '',
                'pending_update_count' => 0,
                'last_error_message' => null,
            ],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->getJson('/telegram/payment-bot/webhook-status')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('configured', true)
        ->assertJsonPath('webhook_is_set', false)
        ->assertJsonPath('can_set_webhook', true)
        ->assertJsonPath('expected_webhook_url', route('telegram.payment-bot.webhook', absolute: true))
        ->assertJsonStructure([
            'expected_webhook_url',
            'matches_expected_url',
            'webhook',
        ]);
});

it('lets an admin set the payment bot webhook', function () {
    config([
        'services.payment_bot.token' => 'payment-test-token',
        'app.url' => 'https://noviquni.test',
    ]);

    Http::fake([
        'api.telegram.org/*/setWebhook' => Http::response([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was set',
        ], 200),
        'api.telegram.org/*/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => 'https://noviquni.test/telegram/payment-bot/webhook',
                'pending_update_count' => 0,
            ],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->post('/telegram/payment-bot/webhook-status')
        ->assertRedirect(route('telegram.payment-bot.webhook-status'));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'setWebhook')
            && str_contains($request->url(), 'payment-test-token')
            && $request['url'] === route('telegram.payment-bot.webhook', absolute: true);
    });
});

it('renders the payment bot status view for admins', function () {
    config(['services.payment_bot.token' => 'payment-test-token']);

    Http::fake([
        'api.telegram.org/*/getWebhookInfo' => Http::response([
            'ok' => true,
            'result' => [
                'url' => '',
                'pending_update_count' => 0,
            ],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->get('/telegram/payment-bot/webhook-status')
        ->assertOk()
        ->assertSee('Set webhook')
        ->assertSee('Payment bot webhook')
        ->assertSee('Main bot webhook');
});

it('accepts payment bot webhook posts without csrf', function () {
    $this->postJson('/telegram/payment-bot/webhook', [
        'update_id' => 1,
        'message' => [
            'message_id' => 1,
            'chat' => ['id' => 1],
            'text' => 'hi',
        ],
    ])->assertOk()
        ->assertSee('ok');
});
