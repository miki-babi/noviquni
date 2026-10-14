<?php

use App\Enums\OnboardingStep;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\TelegramDeepLink;
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

    $enrolled = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Enrolled notes',
    ]);

    $otherCourse = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
    ]);
    LearningResource::factory()->published()->notes()->create([
        'course_id' => $otherCourse->id,
        'stream_id' => $stream->id,
        'title' => 'Other course notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Reference books')
        ->assertSee('Quick saved')
        ->assertSee(route('tg.saved'), false)
        ->assertSee('data-tg-tabbar', false)
        ->assertDontSee('data-tg-menu-button', false)
        ->assertDontSee('Other course notes');

    $this->actingAs($user)
        ->get(route('tg.library.hub', 'notes'))
        ->assertOk()
        ->assertSee('Physics')
        ->assertSee('Enrolled notes')
        ->assertSee($enrolled->miniAppUrl(), false)
        ->assertDontSee(app(TelegramDeepLink::class)->forResource($enrolled->id), false)
        ->assertDontSee('data-tg-bot-link', false)
        ->assertSee('study-card--lime', false)
        ->assertDontSee('Other course notes')
        ->assertDontSee('🔒');
});

it('locks premium resources in the library hub for free users', function () {
    [$user, $course, $stream] = libraryContext();

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Premium notes',
        'is_premium' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.library.hub', 'notes'))
        ->assertOk()
        ->assertSee('Premium notes')
        ->assertSee('🔒');
});

it('shows empty quick saved state and toggles bookmarks', function () {
    [$user, $course, $stream] = libraryContext();

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Savable notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.saved'))
        ->assertOk()
        ->assertSee('Nothing saved in Resources yet', false);

    $this->actingAs($user)
        ->post(route('tg.saved.toggle', $resource))
        ->assertRedirect();

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $resource)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('tg.saved'))
        ->assertOk()
        ->assertSee('Savable notes')
        ->assertSee($resource->miniAppUrl(), false)
        ->assertDontSee(app(TelegramDeepLink::class)->forResource($resource->id), false)
        ->assertDontSee('data-tg-bot-link', false)
        ->assertSee('study-card--lime', false);

    $this->actingAs($user)
        ->from(route('tg.play.reference-books', $resource))
        ->post(route('tg.saved.toggle', $resource))
        ->assertRedirect(route('tg.play.reference-books', $resource));

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $resource)->exists())->toBeFalse();
});

it('shows save control on the notes player', function () {
    [$user, $course, $stream] = libraryContext();

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Player notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.reference-books', $resource))
        ->assertOk()
        ->assertSee('Save', false)
        ->assertSee(route('tg.saved.toggle', $resource), false);
});

it('lists reference books and quiz as separate hubs', function () {
    [$user, $course, $stream] = libraryContext();

    $refBook = LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter notes',
    ]);

    LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter quiz',
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Reference books')
        ->assertSee('Quiz')
        ->assertSee(route('tg.library.hub', 'reference_books'), false);

    $this->actingAs($user)
        ->get(route('tg.library.hub', 'reference_books'))
        ->assertOk()
        ->assertSee('Chapter notes')
        ->assertDontSee('Chapter quiz')
        ->assertSee($refBook->miniAppUrl(), false)
        ->assertDontSee(app(TelegramDeepLink::class)->forResource($refBook->id), false);
});

it('keeps legacy notes hub alias working for reference books resources', function () {
    [$user, $course, $stream] = libraryContext();

    LearningResource::factory()->published()->referenceBooks()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Legacy reference pack',
    ]);

    $this->actingAs($user)
        ->get(route('tg.library.hub', 'notes'))
        ->assertOk()
        ->assertSee('Legacy reference pack');
});
