<?php

use App\Enums\OnboardingStep;
use App\Enums\YearSlug;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Models\Year;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('builds the GAT main keyboard for all students', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $keyboard = app(TelegramService::class)->mainKeyboard($user);

    expect(data_get($keyboard, 'keyboard.0.0.text'))->toBe('Daily challenge')
        ->and(data_get($keyboard, 'keyboard.0.0.web_app'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.0.0.style'))->toBe('success')
        ->and(collect(data_get($keyboard, 'keyboard'))->flatten(1)->pluck('text'))
        ->not->toContain('Continue studying')
        ->and(data_get($keyboard, 'keyboard.1.0.text'))->toBe('Maths')
        ->and(data_get($keyboard, 'keyboard.1.0.style'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.1.1.text'))->toBe('Reading/writing')
        ->and(data_get($keyboard, 'keyboard.2.0.text'))->toBe('Analytical reasoning')
        ->and(data_get($keyboard, 'keyboard.3.0.text'))->toBe('🔖 Quick saved')
        ->and(data_get($keyboard, 'keyboard.3.0.style'))->toBe('primary')
        ->and(data_get($keyboard, 'keyboard.4.0.text'))->toBe('Unlock premium')
        ->and(data_get($keyboard, 'keyboard.4.0.style'))->toBe('success')
        ->and(data_get($keyboard, 'keyboard.5.0.text'))->toBe('Contact us')
        ->and(data_get($keyboard, 'keyboard.5.0.style'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.5.1.text'))->toBe('Refer')
        ->and(data_get($keyboard, 'keyboard.5.1.web_app'))->toBeNull()
        ->and(data_get($keyboard, 'keyboard.5.1.style'))->toBeNull();
});

it('builds the GAT subject reply keyboard', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $keyboard = app(TelegramService::class)->gatSubjectKeyboard($user);

    expect(data_get($keyboard, 'keyboard.0.0.text'))->toBe('Practice exams')
        ->and(data_get($keyboard, 'keyboard.0.1.text'))->toBe('Quiz')
        ->and(data_get($keyboard, 'keyboard.1.0.text'))->toBe('Reference books')
        ->and(data_get($keyboard, 'keyboard.1.1.text'))->toBe('Past exams')
        ->and(data_get($keyboard, 'keyboard.2.0.text'))->toBe('← Back')
        ->and(collect(data_get($keyboard, 'keyboard'))->flatten(1)->pluck('text'))
        ->not->toContain('Daily challenge')
        ->not->toContain('Continue studying')
        ->and(data_get($keyboard, 'resize_keyboard'))->toBeTrue();
});

it('shows the bootstrap page for guests on mini app routes', function () {
    $this->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('tg-session-form', false);
});

it('uses same-host session endpoints from the mini app bootstrap', function () {
    $this->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('action="/tg/session"', false)
        ->assertSee('const diagnoseUrl = "\/tg\/session\/diagnose"', false);
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

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week-1 notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue')
        ->assertDontSee('data-tg-tabbar', false)
        ->assertDontSee('data-tg-menu-button', false)
        ->assertDontSee('Physics')
        ->assertDontSee('My courses');
});

it('defaults the mini app to light mode', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    expect($user->theme)->toBe('light');

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('class="light"', false);
});

it('persists theme preference and renders light class on profile edit', function () {
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
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('class="light"', false)
        ->assertDontSee('data-tg-tabbar', false);
});

it('sends hub routes to the bot-only mini app page', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue');

    $this->actingAs($user)
        ->get(route('tg.home'))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue');

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue');
});

it('routes the profile hub page to the bot-only screen', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'name' => 'Abebe Kebede',
        'telegram_username' => 'abebe',
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue')
        ->assertDontSee('ABEBE KEBEDE');
});

it('shows the premium landing page with gat marketing copy', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    app(SettingsService::class)->set(SettingsService::PAYMENT_BOT_USERNAME, 'noviquni_pay_bot');

    $this->actingAs($user)
        ->get(route('tg.premium'))
        ->assertOk()
        ->assertSee('Included with Premium', false)
        ->assertSee('2,100+ practice questions', false)
        ->assertSee('One-time', false)
        ->assertSee('Get Premium', false)
        ->assertDontSee('Seasonal cohort', false)
        ->assertDontSee('unlock free', false)
        ->assertDontSee('Or unlock', false)
        ->assertSee('data-tg-request-fullscreen="0"', false)
        ->assertSee('https://t.me/noviquni_pay_bot?start=u'.$user->id, false);
});

it('shows the study profile edit form', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    $university = University::factory()->create(['name' => 'AAU']);
    $semester = Semester::factory()->create(['name' => 'Year 1']);
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'university_id' => $university->id,
        'semester_id' => $semester->id,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());
    $user->courses()->sync([$course->id]);

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('Study profile')
        ->assertSee('Natural')
        ->assertSee('AAU')
        ->assertSee('Year 1')
        ->assertSee('Mathematics')
        ->assertSee('Save changes');
});

it('preselects every active course on the study profile when none are enrolled', function () {
    $natural = Stream::factory()->create(['name' => 'Natural']);
    $social = Stream::factory()->create(['name' => 'Social']);
    $math = Course::factory()->create([
        'stream_id' => $natural->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);
    $civics = Course::factory()->create([
        'stream_id' => $social->id,
        'name' => 'Civics',
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $natural->id,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());

    $response = $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('Civics');

    expect($response->getContent())
        ->toContain('value="'.$math->id.'"')
        ->toContain('value="'.$civics->id.'"')
        ->toMatch('/name="course_ids\[\]"\s+value="'.$math->id.'"\s+class="[^"]*"\s+checked/')
        ->toMatch('/name="course_ids\[\]"\s+value="'.$civics->id.'"\s+class="[^"]*"\s+checked/');
});

it('updates academic profile fields from the mini app', function () {
    $natural = Stream::factory()->create(['name' => 'Natural']);
    $social = Stream::factory()->create(['name' => 'Social']);
    $university = University::factory()->create(['name' => 'AAU']);
    $semester = Semester::factory()->create(['name' => 'Year 1']);
    $math = Course::factory()->create([
        'stream_id' => $natural->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);
    $civics = Course::factory()->create([
        'stream_id' => $social->id,
        'name' => 'Civics',
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $natural->id,
        'university_id' => $university->id,
        'semester_id' => $semester->id,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());
    $user->courses()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('tg.profile.academic'), [
            'stream_id' => $social->id,
            'university_id' => '',
            'semester_id' => '',
            'course_ids' => [$civics->id],
        ])
        ->assertRedirect(route('tg.profile'));

    $user->refresh();

    expect($user->stream_id)->toBe($social->id)
        ->and($user->university_id)->toBeNull()
        ->and($user->semester_id)->toBeNull()
        ->and($user->courses()->pluck('courses.id')->all())->toBe([$civics->id]);
});

it('allows courses from another stream on the study profile', function () {
    $natural = Stream::factory()->create(['name' => 'Natural']);
    $social = Stream::factory()->create(['name' => 'Social']);
    $math = Course::factory()->create([
        'stream_id' => $natural->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);
    $civics = Course::factory()->create([
        'stream_id' => $social->id,
        'name' => 'Civics',
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $natural->id,
        'is_active' => true,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Freshman->value)->firstOrFail());
    $user->courses()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('tg.profile.academic'), [
            'stream_id' => $natural->id,
            'course_ids' => [$civics->id],
        ])
        ->assertRedirect(route('tg.profile'));

    expect($user->fresh()->courses()->pluck('courses.id')->all())->toBe([$civics->id]);
});

it('hides department and course pickers for GAT students on the study profile', function () {
    Course::factory()->create(['name' => 'Maths', 'slug' => 'maths', 'is_active' => true]);

    $user = User::factory()->student()->gat()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertDontSee('name="department_name"', false)
        ->assertDontSee('name="stream_id"', false)
        ->assertDontSee('name="course_ids[]"', false)
        ->assertSee('Save changes');
});

it('clears the Courses mini app menu button when the reply keyboard is sent', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555040',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555040, '/start', 40))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/setChatMenuButton')) {
            return false;
        }

        $button = data_get($request->data(), 'menu_button');

        return data_get($button, 'type') === 'commands'
            && data_get($button, 'text') !== 'Courses'
            && data_get($button, 'web_app') === null;
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/setChatMenuButton')
            && data_get($request->data(), 'menu_button.type') === 'web_app'
            && data_get($request->data(), 'menu_button.text') === 'Courses';
    });
});

it('lets non-freshman students update department without attaching courses', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'department_name' => 'Old Department',
        'is_active' => true,
        'is_gat' => false,
    ]);
    $user->syncYear(Year::query()->where('slug', YearSlug::Second->value)->firstOrFail());

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('Department', false)
        ->assertDontSee('name="course_ids[]"', false);

    $this->actingAs($user)
        ->post(route('tg.profile.academic'), [
            'department_name' => 'Electrical Engineering',
            'university_id' => '',
            'semester_id' => '',
        ])
        ->assertRedirect(route('tg.profile'));

    $user->refresh();

    expect($user->department_name)->toBe('Electrical Engineering')
        ->and($user->courses()->count())->toBe(0);
});

it('sends course hub mini app routes back to the bot menu', function () {
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

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue', false);

    $this->actingAs($user)
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'reference_books']))
        ->assertOk()
        ->assertSee('Use the Telegram menu to continue', false);
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
