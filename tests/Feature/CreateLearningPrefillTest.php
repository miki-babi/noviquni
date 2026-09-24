<?php

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Models\Stream;
use App\Models\TelegramFileAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
