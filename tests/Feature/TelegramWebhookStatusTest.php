<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/telegram/webhook-status')
        ->assertUnauthorized();
});

it('forbids students', function () {
    $student = User::factory()->student()->create([
        'email' => 'student@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($student)
        ->getJson('/telegram/webhook-status')
        ->assertForbidden();
});

it('returns webhook status for admins', function () {
    config(['services.telegram.bot_token' => 'test-token']);

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
        ->getJson('/telegram/webhook-status')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('configured', true)
        ->assertJsonPath('webhook_is_set', false)
        ->assertJsonPath('can_set_webhook', true)
        ->assertJsonStructure([
            'expected_webhook_url',
            'matches_expected_url',
            'webhook',
        ]);
});

it('lets an admin set the webhook', function () {
    config([
        'services.telegram.bot_token' => 'test-token',
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
                'url' => 'https://noviquni.test/telegram/webhook',
                'pending_update_count' => 0,
            ],
        ], 200),
    ]);

    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->post('/telegram/webhook-status')
        ->assertRedirect(route('telegram.webhook-status'));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'setWebhook')
            && $request['url'] === route('telegram.webhook', absolute: true);
    });
});

it('renders the status view for admins', function () {
    config(['services.telegram.bot_token' => 'test-token']);

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
        ->get('/telegram/webhook-status')
        ->assertOk()
        ->assertSee('Set webhook')
        ->assertSee('Telegram webhook');
});
