<?php

use App\Enums\ResourceType;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\PremiumService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('allows free published non-premium resources for free students', function () {
    $student = User::factory()->student()->create();
    $resource = LearningResource::factory()->published()->notes()->create([
        'is_premium' => false,
    ]);

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeTrue();
});

it('blocks premium resources for free students', function () {
    $student = User::factory()->student()->create();
    $resource = LearningResource::factory()->published()->premium()->create();

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeFalse();
});

it('blocks flashcards for free students because they are always premium', function () {
    $student = User::factory()->student()->create();
    $module = LearningResource::factory()->published()->module()->create();
    $resource = LearningResource::factory()->published()->flashcards()->create([
        'module_id' => $module->id,
        'course_id' => $module->course_id,
        'stream_id' => $module->stream_id,
        'is_premium' => false,
    ]);

    expect($resource->fresh()->is_premium)->toBeTrue()
        ->and(app(PremiumService::class)->canAccess($student, $resource->fresh()))->toBeFalse();
});

it('allows premium resources when the student has premium', function () {
    $student = User::factory()->student()->premium()->create();
    $resource = LearningResource::factory()->published()->premium()->create();

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeTrue();
});

it('allows flashcards for premium students', function () {
    $student = User::factory()->student()->premium()->create();
    $module = LearningResource::factory()->published()->module()->create();
    $resource = LearningResource::factory()->published()->flashcards()->create([
        'module_id' => $module->id,
        'course_id' => $module->course_id,
        'stream_id' => $module->stream_id,
        'type' => ResourceType::Flashcards,
    ]);

    expect(app(PremiumService::class)->canAccess($student, $resource))->toBeTrue();
});
