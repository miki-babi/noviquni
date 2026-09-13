<?php

use App\Models\LearningResource;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('allows free published resources for any student', function () {
    $student = User::factory()->student()->create();
    $resource = LearningResource::factory()->published()->create([
        'is_premium' => false,
    ]);

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeTrue();
});

it('blocks premium resources without an active subscription', function () {
    $student = User::factory()->student()->create();
    $resource = LearningResource::factory()->published()->premium()->create();

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeFalse();
});

it('allows premium resources when the student has premium', function () {
    $student = User::factory()->student()->premium()->create();
    $resource = LearningResource::factory()->published()->premium()->create();

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeTrue();
});
