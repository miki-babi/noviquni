<?php

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Models\Stream;
use App\Models\TelegramFileAsset;
use App\Models\User;
use App\Support\LearningResourceFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('prefills course and stream from course_id query', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'course_id' => $course->id,
    ])
        ->test(CreateLearning::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'course_id' => $course->id,
            'stream_id' => $stream->id,
        ]);
});

it('prefills telegram vault files from telegram_file_ids query', function () {
    $admin = User::factory()->admin()->create();
    $asset = TelegramFileAsset::factory()->create([
        'file_name' => 'week-1-notes.pdf',
    ]);

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'telegram_file_ids' => $asset->file_id,
        'title' => 'week-1-notes',
    ])
        ->test(CreateLearning::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'file_source' => 'telegram',
            'file_mode' => 'single',
            'telegram_file_ids' => [$asset->file_id],
            'title' => 'week-1-notes',
            'slug' => Str::slug('week-1-notes'),
        ]);
});

it('prefills course library files from existing_files query', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/week-1-notes.pdf';

    Storage::disk($disk)->put($path, 'notes');

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'course_id' => $course->id,
        'existing_files' => $path,
        'title' => 'week-1-notes',
    ])
        ->test(CreateLearning::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'course_id' => $course->id,
            'stream_id' => $stream->id,
            'file_source' => 'existing',
            'file_mode' => 'single',
            'existing_files' => [$path],
            'title' => 'week-1-notes',
            'slug' => Str::slug('week-1-notes'),
        ]);
});

it('keeps prefilled study files when course is re-hydrated to the same value', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/slides.pdf';

    Storage::disk($disk)->put($path, 'slides');

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'existing_files' => $path,
    ])
        ->test(CreateLearning::class)
        ->set('data.course_id', $course->id)
        ->set('data.stream_id', $stream->id)
        ->assertSchemaStateSet([
            'course_id' => $course->id,
            'file_source' => 'existing',
            'existing_files' => [$path],
        ]);
});

it('clears library study files when the course changes', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $otherCourse = Course::factory()->create(['stream_id' => $stream->id]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/slides.pdf';

    Storage::disk($disk)->put($path, 'slides');

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'course_id' => $course->id,
        'existing_files' => $path,
    ])
        ->test(CreateLearning::class)
        ->set('data.course_id', $otherCourse->id)
        ->assertSchemaStateSet([
            'course_id' => $otherCourse->id,
            'existing_files' => [],
        ]);
});

it('ignores invalid course and telegram ids without breaking the page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::withQueryParams([
        'course_id' => 999_999,
        'telegram_file_ids' => 'not-a-real-file-id',
        'title' => 'Suggested title',
    ])
        ->test(CreateLearning::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'title' => 'Suggested title',
            'slug' => Str::slug('Suggested title'),
        ]);
});
