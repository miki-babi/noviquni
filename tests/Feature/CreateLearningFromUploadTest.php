<?php

use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a learning resource from a single uploaded file', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $file = UploadedFile::fake()->create('week-1-notes.pdf', 120, 'application/pdf');

    $this->actingAs($admin);

    Livewire::test(CreateLearning::class)
        ->fillForm([
            'creation_mode' => 'upload',
            'file_mode' => 'single',
            'type' => ResourceType::LectureNotes->value,
            'title' => 'Week 1 Lecture Notes',
            'slug' => 'week-1-lecture-notes',
            'description' => 'Uploaded short notes for week 1.',
            'topics' => ['anthropology', 'week-1'],
            'stream_id' => $stream->id,
            'course_id' => $course->id,
            'sort_order' => 1,
            'is_premium' => false,
            'is_bait' => true,
            'is_published' => false,
            'is_indexable' => true,
            'files' => [$file],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $resource = LearningResource::query()->first();

    expect($resource)->not->toBeNull()
        ->and($resource->title)->toBe('Week 1 Lecture Notes')
        ->and($resource->type)->toBe(ResourceType::LectureNotes)
        ->and($resource->generation_kind)->toBeNull()
        ->and($resource->content)->toBeNull()
        ->and($resource->course_id)->toBe($course->id)
        ->and($resource->is_bait)->toBeTrue()
        ->and($resource->files)->toBeArray()
        ->and($resource->files)->toHaveCount(1)
        ->and($resource->hasFiles())->toBeTrue();

    Storage::disk($disk)->assertExists($resource->files[0]);
});

it('creates a learning resource from multiple uploaded files', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $files = [
        UploadedFile::fake()->create('midterm.pdf', 80, 'application/pdf'),
        UploadedFile::fake()->create('final.pdf', 90, 'application/pdf'),
    ];

    $this->actingAs($admin);

    Livewire::test(CreateLearning::class)
        ->fillForm([
            'creation_mode' => 'upload',
            'file_mode' => 'multiple',
            'type' => ResourceType::PastExam->value,
            'title' => 'Past Exam Pack',
            'slug' => 'past-exam-pack',
            'description' => 'Midterm and final samples.',
            'topics' => ['exams'],
            'stream_id' => $stream->id,
            'course_id' => $course->id,
            'sort_order' => 0,
            'is_premium' => true,
            'is_bait' => false,
            'is_published' => true,
            'is_indexable' => true,
            'files' => $files,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $resource = LearningResource::query()->first();

    expect($resource)->not->toBeNull()
        ->and($resource->type)->toBe(ResourceType::PastExam)
        ->and($resource->generation_kind)->toBeNull()
        ->and($resource->is_premium)->toBeTrue()
        ->and($resource->files)->toHaveCount(2);

    foreach ($resource->files as $path) {
        Storage::disk($disk)->assertExists($path);
    }
});
