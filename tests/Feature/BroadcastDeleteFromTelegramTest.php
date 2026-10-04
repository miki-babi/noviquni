<?php

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config(['services.telegram.bot_token' => 'test-token']);
});

it('stores telegram message ids when sending a broadcast', function () {
    Http::fake([
        'api.telegram.org/*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 4242]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '900101',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Hello everyone',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->send($broadcast);

    $delivery = NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->telegram_message_id)->toBe(4242);
});

it('deletes delivered broadcast messages from telegram', function () {
    Http::fake([
        'api.telegram.org/*/deleteMessage' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '900102',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'status' => BroadcastStatus::Sent,
        'sent_at' => now(),
        'targeting' => ['audience' => 'everyone'],
    ]);

    $delivery = NotificationDelivery::query()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => $user->id,
        'channel' => 'telegram',
        'status' => 'sent',
        'body' => 'Hello',
        'sent_at' => now(),
        'telegram_message_id' => 555,
    ]);

    $counts = app(BroadcastService::class)->deleteFromTelegram($broadcast);

    expect($counts)->toBe(['deleted' => 1, 'failed' => 0, 'skipped' => 0])
        ->and($delivery->fresh()->status)->toBe('deleted');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/deleteMessage')
        && data_get($request->data(), 'chat_id') === '900102'
        && data_get($request->data(), 'message_id') === 555);
});

it('skips deliveries without telegram message ids', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '900103',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'status' => BroadcastStatus::Sent,
        'sent_at' => now(),
    ]);

    NotificationDelivery::query()->create([
        'broadcast_id' => $broadcast->id,
        'user_id' => $user->id,
        'channel' => 'telegram',
        'status' => 'sent',
        'body' => 'Legacy send',
        'sent_at' => now(),
        'telegram_message_id' => null,
    ]);

    $counts = app(BroadcastService::class)->deleteFromTelegram($broadcast);

    expect($counts)->toBe(['deleted' => 0, 'failed' => 0, 'skipped' => 1]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/deleteMessage'));
});
