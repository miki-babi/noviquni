<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\TelegramService;
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

it('builds main keyboard buttons with mini app web_app urls', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $keyboard = app(TelegramService::class)->mainKeyboard($user);

    expect(data_get($keyboard, 'keyboard.0.0.web_app.url'))->toBe(route('tg.continue'))
        ->and(data_get($keyboard, 'keyboard.0.1.web_app.url'))->toBe(route('tg.browse'))
        ->and(data_get($keyboard, 'keyboard.1.0.web_app.url'))->toBe(route('tg.profile'))
        ->and(data_get($keyboard, 'keyboard.1.1.web_app.url'))->toBe(route('tg.premium'));
});

it('shows the bootstrap page for guests on mini app routes', function () {
    $this->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('tg-session-form', false);
});

it('shows browse courses for an authenticated student', function () {
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

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('Physics')
        ->assertDontSee('(0)');
});

it('shows continue resume card when a download exists', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter notes',
    ]);
    $user->downloads()->create(['learning_resource_id' => $resource->id]);

    $this->actingAs($user)
        ->get(route('tg.continue'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('Chapter notes')
        ->assertSee(route('tg.play.notes', $resource), false);
});

it('shows profile and premium screens', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'name' => 'Abebe Kebede',
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('Abebe Kebede');

    $this->actingAs($user)
        ->get(route('tg.premium'))
        ->assertOk()
        ->assertSee('Premium');
});

it('opens a course hub inside the mini app', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);
    LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::Module,
        'title' => 'Module 1',
    ]);

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Chemistry')
        ->assertSee('Notes')
        ->assertSee('Modules');
});

it('authenticates a telegram user from initData and redirects', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555888',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $initData = makeTelegramInitData([
        'id' => 555888,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN');

    $this->post(route('tg.session.store'), [
        'init_data' => $initData,
        'redirect' => route('tg.browse'),
    ])->assertRedirect(route('tg.browse'));

    $this->assertAuthenticatedAs($user);
});
