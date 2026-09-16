<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
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
function miniAppPlayerContext(): array
{
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Anthropology',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    return [$user, $course, $stream];
}

it('renders the notes player for enrolled students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.notes', $resource))
        ->assertOk()
        ->assertSee('Anthropology Notes', false)
        ->assertSee('Anthropology studies humankind across time and space.', false)
        ->assertSee('Key definitions', false);
});

it('renders the quiz player for enrolled students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Quiz',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('Anthropology Quiz', false)
        ->assertSee('What are the Greek roots of anthropology?', false);
});

it('renders the exam player for enrolled students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Exam',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertSee('Anthropology Exam', false)
        ->assertSee('Which statement best describes anthropology?', false);
});

it('renders the flashcards player for enrolled students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Flashcards',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.flashcards', $resource))
        ->assertOk()
        ->assertSee('Anthropology Flashcards', false)
        ->assertSee('What does anthropos mean?', false);
});

it('returns 404 when the player kind does not match the resource', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $flashcards = LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $quiz = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $flashcards))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('tg.play.notes', $quiz))
        ->assertNotFound();
});

it('shows the locked view for premium resources without entitlement', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->premium()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Premium Anthropology Quiz',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('Premium resource', false)
        ->assertDontSee('What are the Greek roots of anthropology?', false);
});

it('returns 404 for unpublished player resources', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'is_published' => false,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertNotFound();
});

it('redirects unenrolled students to browse', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $resource = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertRedirect(route('tg.browse'));
});

it('redirects the resource dispatcher to the matching player', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.resources.show', $resource))
        ->assertRedirect(route('tg.play.quiz', $resource));
});

it('links hub and continue screens to the player url', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter notes',
    ]);
    $user->downloads()->create(['learning_resource_id' => $resource->id]);

    expect($resource->miniAppUrl())->toBe(route('tg.play.notes', $resource));

    $this->actingAs($user)
        ->get(route('tg.continue'))
        ->assertOk()
        ->assertSee(route('tg.play.notes', $resource), false);

    $this->actingAs($user)
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'notes']))
        ->assertOk()
        ->assertSee(route('tg.play.notes', $resource), false);
});

it('keeps the text dump for resources without a study payload', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::Module,
        'title' => 'Module Pack',
        'content' => null,
        'generation_kind' => null,
    ]);

    expect($resource->miniAppRouteName())->toBe('tg.resources.show')
        ->and($resource->miniAppUrl())->toBe(route('tg.resources.show', $resource));

    $this->actingAs($user)
        ->get(route('tg.resources.show', $resource))
        ->assertOk()
        ->assertSee('Module Pack', false);
});
