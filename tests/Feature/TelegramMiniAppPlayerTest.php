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

it('renders the reference books player for enrolled students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $resource = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.reference-books', $resource))
        ->assertOk()
        ->assertSee('Anthropology Notes', false);
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

it('renders the premium quiz player for premium students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['is_premium' => true]);

    $resource = LearningResource::factory()->published()->quiz()->premium()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Premium Quiz',
    ]);

    $this->actingAs($user->fresh())
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('Anthropology Premium Quiz', false)
        ->assertSee('What are the Greek roots of anthropology?', false);
});

it('shows a paywall for premium quiz instead of player content for free students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();
    app(SettingsService::class)->set(SettingsService::PAYMENT_BOT_USERNAME, 'noviquni_pay_bot');

    $resource = LearningResource::factory()->published()->quiz()->premium()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Anthropology Premium Quiz',
        'description' => 'Practice Greek roots and core anthropology definitions.',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('Premium resource', false)
        ->assertSee('Anthropology Premium Quiz', false)
        ->assertSee('Practice Greek roots and core anthropology definitions.', false)
        ->assertSee('Included with Premium', false)
        ->assertSee('One-time', false)
        ->assertSee('Get Premium', false)
        ->assertSee('data-tg-request-fullscreen="0"', false)
        ->assertSee('https://t.me/noviquni_pay_bot?start=u'.$user->id, false)
        ->assertDontSee('What are the Greek roots of anthropology?', false)
        ->assertDontSee(route('tg.premium'), false);
});

it('renders reader shells for every catalog type without interactive payload', function (ResourceType $type, string $routeName) {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['is_premium' => true]);

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => $type,
        'title' => $type->label().' Pack',
        'description' => 'Catalog body for '.$type->value,
        'content' => null,
        'generation_kind' => null,
        'is_premium' => false,
    ]);

    expect($resource->miniAppRouteName())->toBe($routeName)
        ->and($resource->miniAppUrl())->toBe(route($routeName, $resource));

    $this->actingAs($user->fresh())
        ->get(route($routeName, $resource))
        ->assertOk()
        ->assertSee($type->label().' Pack', false);
})->with([
    'quiz' => [ResourceType::Quiz, 'tg.play.quiz'],
    'practice exams' => [ResourceType::PracticeExams, 'tg.play.exam'],
    'past exams' => [ResourceType::PastExams, 'tg.play.exam'],
    'reference books' => [ResourceType::ReferenceBooks, 'tg.play.reference-books'],
]);

it('returns 404 when the player type does not match the resource', function () {
    [$user, $course, $stream] = miniAppPlayerContext();
    $user->update(['is_premium' => true]);

    $quiz = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $referenceBook = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'content' => null,
        'generation_kind' => null,
    ]);

    $this->actingAs($user->fresh())
        ->get(route('tg.play.reference-books', $quiz))
        ->assertNotFound();

    $this->actingAs($user->fresh())
        ->get(route('tg.play.quiz', $referenceBook))
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

it('auto-enrolls students when opening a published player resource', function () {
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
        'title' => 'Enrollment quiz',
    ]);

    expect($user->courses()->where('courses.id', $course->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('Enrollment quiz');

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();
});

it('redirects the resource dispatcher to the matching catalog player', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $quiz = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $referenceBook = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Reference Book Pack',
        'content' => null,
        'generation_kind' => null,
    ]);

    $this->actingAs($user)
        ->get(route('tg.resources.show', $quiz))
        ->assertRedirect(route('tg.play.quiz', $quiz));

    $this->actingAs($user)
        ->get(route('tg.resources.show', $referenceBook))
        ->assertRedirect(route('tg.play.reference-books', $referenceBook));
});

it('opens course hubs for free and premium students', function () {
    [$user, $course, $stream] = miniAppPlayerContext();

    $referenceBook = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'sort_order' => 1,
        'title' => 'Chapter notes',
    ]);

    expect($referenceBook->miniAppUrl())->toBe(route('tg.play.reference-books', $referenceBook));

    $this->actingAs($user->fresh())
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Reference books');

    $this->actingAs($user->fresh())
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'notes']))
        ->assertOk()
        ->assertSee('Chapter notes')
        ->assertSee($referenceBook->miniAppUrl(), false)
        ->assertDontSee('?start=resource_'.$referenceBook->id, false);
});
