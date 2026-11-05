<?php

use App\Models\Broadcast;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('targets all students when audience is everyone', function () {
    $matched = User::factory()->student()->create([
        'telegram_id' => '111',
        'notifications_enabled' => true,
    ]);

    User::factory()->student()->create([
        'telegram_id' => '222',
        'notifications_enabled' => true,
    ]);

    $broadcast = Broadcast::factory()->create([
        'targeting' => [
            'audience' => 'everyone',
        ],
    ]);

    $ids = app(BroadcastService::class)->audienceQuery($broadcast)->pluck('id');

    expect($ids)->toContain($matched->id)
        ->and($ids)->toHaveCount(2);
});

it('personalizes broadcast variables', function () {
    $user = User::factory()->student()->create([
        'name' => 'Hana Bekele',
    ]);

    $body = app(BroadcastService::class)->personalize(
        'Hi {{first_name}} — resources are ready.',
        $user,
    );

    expect($body)->toBe('Hi Hana — resources are ready.');
});
