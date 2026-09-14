<?php

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcastJob;
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

it('sends broadcasts synchronously without queueing jobs', function () {
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
        ->and($delivery->sent_at)->not->toBeNull();

    Queue::assertNothingPushed();
    Queue::assertNotPushed(SendBroadcastJob::class);
    Queue::assertNotPushed(SendTelegramMessageJob::class);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && data_get($request->data(), 'chat_id') === '900001'
            && str_contains((string) data_get($request->data(), 'text'), 'Hi Abebe');
    });
});

it('can resend an already sent broadcast', function () {
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
        ->and(NotificationDelivery::query()->where('broadcast_id', $broadcast->id)->count())->toBe(1);

    Queue::assertNothingPushed();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && data_get($request->data(), 'chat_id') === '900002');
});
