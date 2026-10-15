<?php

use App\Filament\Resources\Learnings\Pages\ListLearnings;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\SettingsService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('makes selected learnings premium in bulk', function () {
    $admin = User::factory()->admin()->create();
    $resources = LearningResource::factory()->count(3)->create([
        'is_premium' => false,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListLearnings::class)
        ->assertCanSeeTableRecords($resources)
        ->selectTableRecords($resources)
        ->callAction(TestAction::make('makePremium')->table()->bulk())
        ->assertNotified();

    $resources->each(fn (LearningResource $resource) => expect($resource->fresh()->is_premium)->toBeTrue());
});

it('makes selected learnings free in bulk', function () {
    $admin = User::factory()->admin()->create();
    $resources = LearningResource::factory()->count(2)->premium()->create();

    $this->actingAs($admin);

    Livewire::test(ListLearnings::class)
        ->assertCanSeeTableRecords($resources)
        ->selectTableRecords($resources)
        ->callAction(TestAction::make('makeFree')->table()->bulk())
        ->assertNotified();

    $resources->each(fn (LearningResource $resource) => expect($resource->fresh()->is_premium)->toBeFalse());
});
