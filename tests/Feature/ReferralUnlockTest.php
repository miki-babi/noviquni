<?php

use App\Models\User;
use App\Services\ReferralService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('defaults required referrals to 3', function () {
    expect(app(SettingsService::class)->requiredReferrals())->toBe(3);
});

it('attributes a referral and unlocks premium after required referrals', function () {
    $referrer = User::factory()->student()->create();

    foreach (range(1, 3) as $i) {
        $referred = User::factory()->student()->create([
            'name' => "Student {$i}",
        ]);

        app(ReferralService::class)->attributeReferral($referred, $referrer->referral_code);
    }

    expect($referrer->fresh()->hasActivePremium())->toBeTrue()
        ->and(app(ReferralService::class)->qualifiedCount($referrer->fresh()))->toBeGreaterThanOrEqual(3);
});

it('does not unlock premium before reaching the required referral count', function () {
    $referrer = User::factory()->student()->create();

    foreach (range(1, 2) as $i) {
        $referred = User::factory()->student()->create([
            'name' => "Student {$i}",
        ]);

        app(ReferralService::class)->attributeReferral($referred, $referrer->referral_code);
    }

    expect($referrer->fresh()->hasActivePremium())->toBeFalse();
});

it('ignores self referrals', function () {
    $user = User::factory()->student()->create();

    $result = app(ReferralService::class)->attributeReferral($user, $user->referral_code);

    expect($result)->toBeNull()
        ->and($user->fresh()->referred_by_user_id)->toBeNull();
});
