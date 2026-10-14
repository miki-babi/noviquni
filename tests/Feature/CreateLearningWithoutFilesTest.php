<?php

use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a learning resource without study files', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create([
        'name' => 'Maths',
        'slug' => 'maths',
        'stream_id' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(CreateLearning::class)
        ->fillForm([
            'type' => ResourceType::ReferenceBooks->value,
            'title' => 'Algebra notes',
            'slug' => 'algebra-notes',
            'course_id' => $course->id,
            'sort_order' => 0,
            'is_premium' => false,
            'is_published' => true,
            'is_indexable' => true,
            'file_source' => 'telegram',
            'telegram_file_ids' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $resource = LearningResource::query()->where('slug', 'algebra-notes')->first();

    expect($resource)->not->toBeNull()
        ->and($resource->course_id)->toBe($course->id)
        ->and($resource->stream_id)->toBeNull()
        ->and($resource->files)->toBeNull()
        ->and($resource->telegram_files)->toBeNull()
        ->and($resource->hasFiles())->toBeFalse();
});
