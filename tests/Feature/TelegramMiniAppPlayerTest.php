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

    $resource = LearningResource::factory()->bait()->notes()->create([
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

    $resource = LearningResource::factory()->bait()->quiz()->create([
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

    $resource = LearningResource::factory()->bait()->exam()->create([
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

it('renders the flashcards player for premium students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['premium_until' => now()->addDays(7)]);

    $module = LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $resource = LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Anthropology Flashcards',
    ]);

    $this->actingAs($user->fresh())
        ->get(route('tg.play.flashcards', $resource))
        ->assertOk()
        ->assertSee('Anthropology Flashcards', false)
        ->assertSee('What does anthropos mean?', false)
        ->assertSee('Tap to reveal answer', false);
});

it('locks flashcards for free students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $module = LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $resource = LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Anthropology Flashcards',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.flashcards', $resource))
        ->assertOk()
        ->assertSee('Premium resource', false)
        ->assertDontSee('What does anthropos mean?', false);
});

it('renders reader shells for every catalog type without interactive payload', function (ResourceType $type, string $routeName) {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['premium_until' => now()->addDays(7)]);

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => $type,
        'title' => $type->label().' Pack',
        'description' => 'Catalog body for '.$type->value,
        'content' => null,
        'generation_kind' => null,
        'is_bait' => false,
        'is_premium' => $type === ResourceType::Flashcards,
    ]);

    expect($resource->miniAppRouteName())->toBe($routeName)
        ->and($resource->miniAppUrl())->toBe(route($routeName, $resource));

    $this->actingAs($user->fresh())
        ->get(route($routeName, $resource))
        ->assertOk()
        ->assertSee($type->label().' Pack', false)
        ->assertSee('Catalog body for '.$type->value, false);
})->with([
    'module' => [ResourceType::Module, 'tg.play.module'],
    'notes' => [ResourceType::Notes, 'tg.play.notes'],
    'worksheet' => [ResourceType::Worksheet, 'tg.play.worksheet'],
    'assignment' => [ResourceType::Assignment, 'tg.play.assignment'],
    'quiz' => [ResourceType::Quiz, 'tg.play.quiz'],
    'practice exams' => [ResourceType::PracticeExams, 'tg.play.exam'],
    'mid exam' => [ResourceType::MidExam, 'tg.play.exam'],
    'final exam' => [ResourceType::FinalExam, 'tg.play.exam'],
    'slides' => [ResourceType::Slides, 'tg.play.slides'],
    'reference books' => [ResourceType::ReferenceBooks, 'tg.play.reference-books'],
    'flashcards' => [ResourceType::Flashcards, 'tg.play.flashcards'],
]);

it('returns 404 when the player type does not match the resource', function () {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['premium_until' => now()->addDays(7)]);

    $flashcards = LearningResource::factory()->published()->flashcards()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $module = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::Module,
        'content' => null,
        'generation_kind' => null,
    ]);

    $this->actingAs($user->fresh())
        ->get(route('tg.play.quiz', $flashcards))
        ->assertNotFound();

    $this->actingAs($user->fresh())
        ->get(route('tg.play.notes', $module))
        ->assertNotFound();

    $this->actingAs($user->fresh())
        ->get(route('tg.play.module', $flashcards))
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

    $resource = LearningResource::factory()->bait()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertRedirect(route('tg.browse'));
});

it('redirects the resource dispatcher to the matching catalog player', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $quiz = LearningResource::factory()->bait()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $module = LearningResource::factory()->bait()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::Module,
        'title' => 'Module Pack',
        'content' => null,
        'generation_kind' => null,
    ]);

    $this->actingAs($user)
        ->get(route('tg.resources.show', $quiz))
        ->assertRedirect(route('tg.play.quiz', $quiz));

    $this->actingAs($user)
        ->get(route('tg.resources.show', $module))
        ->assertRedirect(route('tg.play.module', $module));
});

it('opens course hubs for free and premium students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $module = LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'sort_order' => 1,
        'title' => 'Module 1',
    ]);

    $notes = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'module_id' => $module->id,
        'title' => 'Chapter notes',
    ]);

    expect($notes->miniAppUrl())->toBe(route('tg.play.notes', $notes));

    $this->actingAs($user->fresh())
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Notes')
        ->assertSee('Modules');

    $this->actingAs($user->fresh())
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'notes']))
        ->assertOk()
        ->assertSee(route('tg.play.notes', $notes), false);
});
