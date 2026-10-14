<?php

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a course without a stream from the admin form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(CreateCourse::class)
        ->fillForm([
            'name' => 'Maths',
            'slug' => 'maths',
            'is_active' => true,
            'is_indexable' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $course = Course::query()->where('slug', 'maths')->first();

    expect($course)->not->toBeNull()
        ->and($course->name)->toBe('Maths')
        ->and($course->stream_id)->toBeNull();
});
