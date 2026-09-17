<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\CoursePathService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('orders the course path as module then children then exams', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $module = LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'sort_order' => 1,
        'title' => 'Module A',
    ]);

    LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Flashcards A',
    ]);

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Notes A',
    ]);

    LearningResource::factory()->published()->worksheet()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Worksheet A',
    ]);

    LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Quiz A',
    ]);

    LearningResource::factory()->published()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Past Mid',
    ]);

    $steps = app(CoursePathService::class)->orderedSteps($course);

    expect($steps->pluck('title')->all())->toBe([
        'Module A',
        'Notes A',
        'Worksheet A',
        'Quiz A',
        'Flashcards A',
        'Past Mid',
    ]);
});

it('returns the first unopened accessible step as next for free students', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->sync([$course->id]);

    $baitNotes = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Bait notes',
    ]);

    LearningResource::factory()->published()->premium()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Full quiz',
    ]);

    $next = app(CoursePathService::class)->nextStep($user, $course);

    expect($next?->id)->toBe($baitNotes->id);
});

it('locks non-bait path steps for free students in the mini app course view', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Free note',
    ]);

    LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Locked module',
        'type' => ResourceType::Module,
    ]);

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Free note')
        ->assertSee('Locked module')
        ->assertSee('🔒', false);
});
