<?php

use App\Models\Broadcast;
use App\Models\Stream;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('targets students by stream', function () {
    $streamA = Stream::factory()->create(['name' => 'Natural']);
    $streamB = Stream::factory()->create(['name' => 'Social']);

    $matched = User::factory()->student()->create([
        'stream_id' => $streamA->id,
        'telegram_id' => '111',
        'notifications_enabled' => true,
    ]);

    User::factory()->student()->create([
        'stream_id' => $streamB->id,
        'telegram_id' => '222',
        'notifications_enabled' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'targeting' => [
            'audience' => 'everyone',
            'stream_id' => $streamA->id,
        ],
    ]);

    $ids = app(BroadcastService::class)->audienceQuery($broadcast)->pluck('id');

    expect($ids)->toContain($matched->id)
        ->and($ids)->toHaveCount(1);
});

it('personalizes broadcast variables', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    $user = User::factory()->student()->create([
        'name' => 'Hana Bekele',
        'stream_id' => $stream->id,
    ]);

    $body = app(BroadcastService::class)->personalize(
        'Hi {{first_name}} — {{stream}} resources are ready.',
        $user,
    );

    expect($body)->toBe('Hi Hana — Natural resources are ready.');
});
