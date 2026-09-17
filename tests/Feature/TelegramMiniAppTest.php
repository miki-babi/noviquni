<?php

use App\Enums\OnboardingStep;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('builds main keyboard buttons with courses resources saved profile and refer', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $keyboard = app(TelegramService::class)->mainKeyboard($user);

    expect(data_get($keyboard, 'keyboard.0.0.text'))->toBe('📚 Courses')
        ->and(data_get($keyboard, 'keyboard.0.0.web_app.url'))->toBe(route('tg.browse'))
        ->and(data_get($keyboard, 'keyboard.0.0.style'))->toBe('success')
        ->and(data_get($keyboard, 'keyboard.0.1.text'))->toBe('📖 Resources')
        ->and(data_get($keyboard, 'keyboard.0.1.web_app.url'))->toBe(route('tg.library'))
        ->and(data_get($keyboard, 'keyboard.0.1.style'))->toBe('success')
        ->and(data_get($keyboard, 'keyboard.1.0.text'))->toBe('🔖 Quick saved')
        ->and(data_get($keyboard, 'keyboard.1.0.web_app'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.1.0.style'))->toBe('primary')
        ->and(data_get($keyboard, 'keyboard.2.0.text'))->toBe('👤 Profile')
        ->and(data_get($keyboard, 'keyboard.2.0.web_app'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.2.0.style'))->toBe('primary')
        ->and(data_get($keyboard, 'keyboard.2.1.text'))->toBe('👥 Refer and earn')
        ->and(data_get($keyboard, 'keyboard.2.1.web_app'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.2.1.style'))->toBe('primary');
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
        'name' => 'Abebe Kebede',
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week-1 notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('Courses')
        ->assertSee('Continue')
        ->assertSee('Resume →')
        ->assertSee('Physics')
        ->assertSee('Week-1 notes')
        ->assertSee('My courses')
        ->assertSee('1 resources')
        ->assertSee('+ Add another course')
        ->assertSee($resource->miniAppUrl(), false)
        ->assertSee('data-tg-menu-button', false)
        ->assertSee('Resources')
        ->assertSee('Profile')
        ->assertSee('Abebe Kebede')
        ->assertDontSee('>Study<', false)
        ->assertDontSee('>Account<', false)
        ->assertDontSee('>Other<', false)
        ->assertDontSee('0/1')
        ->assertDontSee('1/2')
        ->assertDontSee('Next: Week-1 notes')
        ->assertDontSee('grid-cols-4', false);

    $html = $this->actingAs($user)->get(route('tg.browse'))->getContent();
    expect($html)->not->toMatch('/tg-page-title[^>]*>[^<]*<\/h1>\s*<p[^>]*tg-hint/')
        ->and($html)->toContain('class="dark"');
});

it('persists theme preference and renders light class', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'theme' => 'dark',
    ]);

    $this->actingAs($user)
        ->post(route('tg.profile.theme'), ['theme' => 'light'])
        ->assertRedirect();

    expect($user->fresh()->theme)->toBe('light');

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('class="light"', false)
        ->assertSee('Premium')
        ->assertSee('Theme');
});

it('shows saved link on resources page', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Quick saved')
        ->assertSee(route('tg.saved'), false);
});

it('shows continue next path step for bait resources', function () {
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

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Chapter notes',
    ]);

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
        'telegram_username' => 'abebe',
        'telegram_photo_url' => 'https://t.me/i/userpic/320/abebe.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('Abebe Kebede')
        ->assertSee('@abebe')
        ->assertSee('https://t.me/i/userpic/320/abebe.jpg', false)
        ->assertSee('Premium')
        ->assertSee(route('tg.premium'), false);

    $this->actingAs($user)
        ->get(route('tg.premium'))
        ->assertOk()
        ->assertSee('Premium');
});

it('opens a course study path inside the mini app', function () {
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

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week-1 notes',
    ]);
    LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Module 1',
    ]);

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Chemistry')
        ->assertSee('Week-1 notes')
        ->assertSee('Module 1')
        ->assertSee('data-tg-header-back', false)
        ->assertDontSee('data-tg-nav-bar', false)
        ->assertSee(route('tg.browse'), false)
        ->assertDontSee('Archive');
});

it('authenticates a telegram user from initData and redirects', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555888',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'name' => 'Old Name',
    ]);

    $initData = makeTelegramInitData([
        'id' => 555888,
        'first_name' => 'Abebe',
        'last_name' => 'Kebede',
        'username' => 'abebe_k',
        'photo_url' => 'https://cdn.example.test/photo.jpg',
    ], '123456:TEST_TOKEN');

    $this->post(route('tg.session.store'), [
        'init_data' => $initData,
        'redirect' => route('tg.browse'),
    ])->assertRedirect(route('tg.browse'));

    $this->assertAuthenticatedAs($user);

    expect($user->fresh())
        ->name->toBe('Abebe Kebede')
        ->telegram_username->toBe('abebe_k')
        ->telegram_photo_url->toBe('https://cdn.example.test/photo.jpg');
});

it('rewrites keyboard mini app hosts when TELEGRAM_MINI_APP_URL is set', function () {
    config(['services.telegram.mini_app_url' => 'https://mini.example.test']);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $url = app(TelegramService::class)->miniAppUrl('tg.browse');

    expect($url)->toBe('https://mini.example.test/tg/browse')
        ->and(app(TelegramService::class)->miniAppUrl('tg.library'))->toBe('https://mini.example.test/tg/library')
        ->and(app(TelegramService::class)->miniAppUrl('tg.saved'))->toBe('https://mini.example.test/tg/saved')
        ->and(app(TelegramService::class)->miniAppUrl('tg.profile'))->toBe('https://mini.example.test/tg/profile');
});

it('accepts mini app redirects whose path is under /tg even on another host', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555889',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $initData = makeTelegramInitData([
        'id' => 555889,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN');

    $this->post(route('tg.session.store'), [
        'init_data' => $initData,
        'redirect' => 'https://mini.example.test/tg/library',
    ])->assertRedirect('https://mini.example.test/tg/library');

    $this->assertAuthenticatedAs($user);
});

it('rejects redirects that are not under the /tg path', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555890',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $initData = makeTelegramInitData([
        'id' => 555890,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN');

    $this->post(route('tg.session.store'), [
        'init_data' => $initData,
        'redirect' => 'https://evil.example/phish',
    ])->assertRedirect(route('tg.home'));

    $this->assertAuthenticatedAs($user);
});

it('logs guest bootstrap and client diagnose events', function () {
    $logged = collect();

    Event::listen(
        MessageLogged::class,
        function (MessageLogged $event) use ($logged): void {
            $logged->push($event);
        }
    );

    $this->get(route('tg.browse'))->assertOk();

    expect($logged->contains(
        fn (MessageLogged $event): bool => $event->level === 'info'
            && $event->message === 'Telegram mini-app guest bootstrap'
    ))->toBeTrue();

    $this->postJson(route('tg.session.diagnose'), [
        'event' => 'init_data_missing',
        'has_telegram' => true,
        'has_init_data' => false,
        'init_data_length' => 0,
        'tg_platform' => 'android',
        'tg_version' => '8.0',
        'path' => '/tg/browse',
        'waited_ms' => 1500,
    ])->assertOk()->assertJson(['ok' => true]);

    expect($logged->contains(
        fn (MessageLogged $event): bool => $event->level === 'info'
            && $event->message === 'Telegram mini-app client diagnose'
            && ($event->context['event'] ?? null) === 'init_data_missing'
    ))->toBeTrue();
});

it('logs session store failures without exposing initData', function () {
    $logged = collect();

    Event::listen(
        MessageLogged::class,
        function (MessageLogged $event) use ($logged): void {
            $logged->push($event);
        }
    );

    User::factory()->student()->create([
        'telegram_id' => '555891',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $initData = makeTelegramInitData([
        'id' => 555891,
        'first_name' => 'Abebe',
    ], 'wrong-token');

    $this->from(route('tg.browse'))
        ->post(route('tg.session.store'), [
            'init_data' => $initData,
            'redirect' => route('tg.browse'),
        ])
        ->assertRedirect();

    $failure = $logged->first(
        fn (MessageLogged $event): bool => $event->level === 'warning'
            && $event->message === 'Telegram mini-app session.store failed'
    );

    expect($failure)->not->toBeNull()
        ->and($failure->context['reason'] ?? null)->toBe('Telegram initData signature is invalid.')
        ->and(json_encode($failure->context))->not->toContain($initData);
});
