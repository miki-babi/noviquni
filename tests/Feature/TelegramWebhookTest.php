<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
    ]);
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('accepts a start update and creates a student', function () {
    $payload = [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'text' => '/start',
            'chat' => ['id' => 555001],
            'from' => [
                'id' => 555001,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ];

    $this->postJson('/telegram/webhook', $payload)
        ->assertOk();

    expect(User::query()->where('telegram_id', '555001')->exists())->toBeTrue();
});
