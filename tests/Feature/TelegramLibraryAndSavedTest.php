<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

/**
 * @return array{0: User, 1: Course, 2: Stream}
 */
function libraryContext(): array
{
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    return [$user, $course, $stream];
}

it('lists resource hubs across enrolled courses only', function () {
    [$user, $course, $stream] = libraryContext();

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Enrolled notes',
    ]);

    $otherCourse = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
    ]);
    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $otherCourse->id,
        'stream_id' => $stream->id,
        'title' => 'Other course notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Notes')
        ->assertDontSee('Other course notes');

    $this->actingAs($user)
        ->get(route('tg.library.hub', 'notes'))
        ->assertOk()
        ->assertSee('Enrolled notes')
        ->assertDontSee('Other course notes');
});

it('shows empty quick saved state and toggles bookmarks', function () {
    [$user, $course, $stream] = libraryContext();

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Savable notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.saved'))
        ->assertOk()
        ->assertSee('Nothing saved yet', false);

    $this->actingAs($user)
        ->post(route('tg.saved.toggle', $resource))
        ->assertRedirect();

    expect(Bookmark::query()->where('user_id', $user->id)->where('learning_resource_id', $resource->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('tg.saved'))
        ->assertOk()
        ->assertSee('Savable notes');

    $this->actingAs($user)
        ->from(route('tg.play.notes', $resource))
        ->post(route('tg.saved.toggle', $resource))
        ->assertRedirect(route('tg.play.notes', $resource));

    expect(Bookmark::query()->where('user_id', $user->id)->where('learning_resource_id', $resource->id)->exists())->toBeFalse();
});

it('shows save control on the notes player', function () {
    [$user, $course, $stream] = libraryContext();

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::LectureNotes,
        'title' => 'Player notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.notes', $resource))
        ->assertOk()
        ->assertSee('Save', false)
        ->assertSee(route('tg.saved.toggle', $resource), false);
});
