<?php

use App\Enums\BroadcastStatus;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Broadcast;
use App\Models\NotificationDelivery;
use App\Models\Stream;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config(['services.telegram.bot_token' => 'test-token']);
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
    ]);
});

it('sends broadcasts synchronously without pending deliveries', function () {
    Queue::fake();

    $stream = Stream::factory()->create(['name' => 'Natural']);

    $user = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => '900001',
        'stream_id' => $stream->id,
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Hi {{first_name}} — {{stream}} updates are ready.',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->send($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Sent)
        ->and($broadcast->sent_at)->not->toBeNull();

    $delivery = NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->user_id)->toBe($user->id)
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->body)->toBe('Hi Abebe — Natural updates are ready.')
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->telegram_message_id)->toBe(1)
        ->and(NotificationDelivery::query()->where('status', 'pending')->count())->toBe(0);

    Queue::assertNothingPushed();
    Queue::assertNotPushed(SendTelegramMessageJob::class);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && data_get($request->data(), 'chat_id') === '900001'
            && str_contains((string) data_get($request->data(), 'text'), 'Hi Abebe');
    });
});

it('can resend an already sent broadcast synchronously', function () {
    Queue::fake();

    User::factory()->student()->create([
        'telegram_id' => '900002',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Reminder for everyone.',
        'status' => BroadcastStatus::Sent,
        'sent_at' => now()->subHour(),
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->send($broadcast);

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Sent)
        ->and(NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('status', 'pending')->count())->toBe(0);

    Queue::assertNothingPushed();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && data_get($request->data(), 'chat_id') === '900002');
});

it('queues broadcast deliveries as pending rows without sending yet', function () {
    Queue::fake();

    $user = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => '900010',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Hi {{first_name}}.',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    $queued = app(BroadcastService::class)->queueSend($broadcast);

    $broadcast->refresh();
    $delivery = NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->first();

    expect($queued)->toBeTrue()
        ->and($broadcast->status)->toBe(BroadcastStatus::Sending)
        ->and($broadcast->scheduled_at)->toBeNull()
        ->and($delivery)->not->toBeNull()
        ->and($delivery->user_id)->toBe($user->id)
        ->and($delivery->status)->toBe('pending')
        ->and($delivery->body)->toBe('Hi Abebe.');

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('does not queue a broadcast that is already sending', function () {
    Queue::fake();

    $broadcast = Broadcast::factory()->create([
        'body' => 'Already going.',
        'status' => BroadcastStatus::Sending,
        'targeting' => ['audience' => 'everyone'],
    ]);

    $queued = app(BroadcastService::class)->queueSend($broadcast);

    expect($queued)->toBeFalse()
        ->and(NotificationDelivery::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('processes pending deliveries and marks the broadcast sent', function () {
    $user = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => '900011',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Hi {{first_name}}.',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->queueSend($broadcast);

    $processed = app(BroadcastService::class)->processPendingDeliveries(50);

    $broadcast->refresh();
    $delivery = NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->first();

    expect($processed)->toBe(1)
        ->and($broadcast->status)->toBe(BroadcastStatus::Sent)
        ->and($broadcast->sent_at)->not->toBeNull()
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->user_id)->toBe($user->id)
        ->and($delivery->body)->toBe('Hi Abebe.')
        ->and($delivery->telegram_message_id)->toBe(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && data_get($request->data(), 'chat_id') === '900011');
});

it('processes pending deliveries in batches of fifty', function () {
    User::factory()->student()->count(BroadcastService::PendingDeliveryBatchSize + 5)->create();

    $broadcast = Broadcast::factory()->create([
        'body' => 'Batch page.',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->queueSend($broadcast);

    expect(NotificationDelivery::query()->where('status', 'pending')->count())
        ->toBe(BroadcastService::PendingDeliveryBatchSize + 5);

    $processed = app(BroadcastService::class)->processPendingDeliveries();

    $broadcast->refresh();

    expect($processed)->toBe(BroadcastService::PendingDeliveryBatchSize)
        ->and($broadcast->status)->toBe(BroadcastStatus::Sending)
        ->and(NotificationDelivery::query()->where('status', 'sent')->count())
        ->toBe(BroadcastService::PendingDeliveryBatchSize)
        ->and(NotificationDelivery::query()->where('status', 'pending')->count())->toBe(5);

    app(BroadcastService::class)->processPendingDeliveries();

    $broadcast->refresh();

    expect($broadcast->status)->toBe(BroadcastStatus::Sent)
        ->and(NotificationDelivery::query()->where('status', 'pending')->count())->toBe(0);
});

it('runs the process-pending artisan command', function () {
    User::factory()->student()->create([
        'telegram_id' => '900012',
        'notifications_enabled' => true,
        'is_active' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'body' => 'Via artisan.',
        'status' => BroadcastStatus::Draft,
        'targeting' => ['audience' => 'everyone'],
    ]);

    app(BroadcastService::class)->queueSend($broadcast);

    $this->artisan('broadcasts:process-pending', ['--limit' => 50])
        ->assertSuccessful();

    expect($broadcast->fresh()->status)->toBe(BroadcastStatus::Sent);
});
