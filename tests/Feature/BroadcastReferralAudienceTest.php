<?php

use App\Enums\ReferralStatus;
use App\Models\Broadcast;
use App\Models\Referral;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('targets users by minimum qualified referral count', function () {
    $withTwo = User::factory()->student()->create([
        'telegram_id' => '1001',
        'notifications_enabled' => true,
    ]);
    $withZero = User::factory()->student()->create([
        'telegram_id' => '1002',
        'notifications_enabled' => true,
    ]);

    foreach (range(1, 2) as $i) {
        Referral::factory()->create([
            'referrer_id' => $withTwo->id,
            'referred_id' => User::factory()->student()->create([
                'telegram_id' => null,
                'notifications_enabled' => false,
            ])->id,
            'status' => ReferralStatus::Qualified,
        ]);
    }

    $broadcast = Broadcast::factory()->create([
        'targeting' => [
            'audience' => 'everyone',
            'min_referrals' => 2,
        ],
    ]);

    $ids = app(BroadcastService::class)->audienceQuery($broadcast)->pluck('id');

    expect($ids)->toContain($withTwo->id)
        ->and($ids)->not->toContain($withZero->id)
        ->and($ids)->toHaveCount(1);
});

it('targets users by maximum qualified referral count', function () {
    $withOne = User::factory()->student()->create([
        'telegram_id' => '2001',
        'notifications_enabled' => true,
    ]);
    $withThree = User::factory()->student()->create([
        'telegram_id' => '2002',
        'notifications_enabled' => true,
    ]);

    Referral::factory()->create([
        'referrer_id' => $withOne->id,
        'referred_id' => User::factory()->student()->create([
            'telegram_id' => null,
            'notifications_enabled' => false,
        ])->id,
        'status' => ReferralStatus::Qualified,
    ]);

    foreach (range(1, 3) as $i) {
        Referral::factory()->create([
            'referrer_id' => $withThree->id,
            'referred_id' => User::factory()->student()->create([
                'telegram_id' => null,
                'notifications_enabled' => false,
            ])->id,
            'status' => ReferralStatus::Completed,
        ]);
    }

    $broadcast = Broadcast::factory()->create([
        'targeting' => [
            'audience' => 'everyone',
            'max_referrals' => 1,
        ],
    ]);

    $ids = app(BroadcastService::class)->audienceQuery($broadcast)->pluck('id');

    expect($ids)->toContain($withOne->id)
        ->and($ids)->not->toContain($withThree->id);
});

it('personalizes referral count and points variables', function () {
    $user = User::factory()->student()->create([
        'name' => 'Hana Bekele',
        'referral_points' => 4,
    ]);

    Referral::factory()->create([
        'referrer_id' => $user->id,
        'referred_id' => User::factory()->student()->create()->id,
        'status' => ReferralStatus::Qualified,
    ]);

    $body = app(BroadcastService::class)->personalize(
        'Hi {{first_name}} — you have {{referral_count}} referrals and {{referral_points}} points.',
        $user,
    );

    expect($body)->toBe('Hi Hana — you have 1 referrals and 4 points.');
});
