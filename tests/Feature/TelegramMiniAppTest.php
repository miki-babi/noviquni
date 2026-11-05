<?php

use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

it('hides the unlock premium row for premium students', function () {
    $user = User::factory()->student()->create([
        'is_premium' => true,
    ]);

    $keyboard = app(TelegramService::class)->mainKeyboard($user);

    $buttonTexts = collect(data_get($keyboard, 'keyboard'))->flatten(1)->pluck('text');

    expect($buttonTexts)->not->toContain('Unlock premium')
        ->and(data_get($keyboard, 'keyboard.0.0.text'))->toBe('Daily challenge')
        ->and(data_get($keyboard, 'keyboard.1.0.text'))->toBe('Maths')
        ->and(data_get($keyboard, 'keyboard.2.0.text'))->toBe('Analytical reasoning')
        ->and(data_get($keyboard, 'keyboard.3.0.text'))->toBe('🔖 Quick saved')
        ->and(data_get($keyboard, 'keyboard.4.0.text'))->toBe('Contact us')
        ->and(data_get($keyboard, 'keyboard.4.1.text'))->toBe('Refer');
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
    Queue::fake();

    $course = Course::factory()->create([

        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([

        'is_active' => true,
        'name' => 'Abebe Kebede',
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->notes()->create([
        'course_id' => $course->id,

        'title' => 'Week-1 notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('Physics')
        ->assertSee(route('tg.courses.show', $course), false)
        ->assertDontSee('Use the Telegram menu to continue');
});

it('defaults the mini app to light mode', function () {
    $user = User::factory()->student()->create([
        'is_active' => true,
    ]);

    expect($user->theme)->toBe('light');

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('class="light"', false);
});

it('persists theme preference and renders light class on profile', function () {
    $user = User::factory()->student()->create([
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
        ->assertDontSee('data-tg-tabbar', false);
});

it('renders the mini app hub pages for students', function () {
    $user = User::factory()->student()->create([
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.library'))
        ->assertOk()
        ->assertSee('Resources');

    $this->actingAs($user)
        ->get(route('tg.home'))
        ->assertRedirect(route('tg.browse'));

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk();
});

it('shows the student profile page in the mini app', function () {
    $user = User::factory()->student()->create([
        'is_active' => true,
        'name' => 'Abebe Kebede',
        'telegram_username' => 'abebe',
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile'))
        ->assertOk()
        ->assertSee('ABEBE KEBEDE')
        ->assertSee('@abebe');
});

it('shows the premium landing page with gat marketing copy', function () {
    $user = User::factory()->student()->create([
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
        ->assertSee('https://t.me/noviquni_pay_bot?start=u'.$user->id, false);
});

it('sets the Courses mini app menu button when the reply keyboard is sent', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555040',
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555040, '/start', 40))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/setChatMenuButton')) {
            return false;
        }

        $button = data_get($request->data(), 'menu_button');

        return data_get($button, 'type') === 'web_app'
            && data_get($button, 'text') === 'Courses'
            && filled(data_get($button, 'web_app.url'));
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/setChatMenuButton')
            && data_get($request->data(), 'menu_button.type') === 'commands';
    });
});

it('renders course pages inside the mini app', function () {
    $course = Course::factory()->create([

        'name' => 'Chemistry',
    ]);

    $user = User::factory()->student()->create([

        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->actingAs($user)
        ->get(route('tg.courses.show', $course))
        ->assertOk()
        ->assertSee('Chemistry');

    $this->actingAs($user)
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'reference_books']))
        ->assertOk()
        ->assertSee('Reference books');
});

it('authenticates a telegram user from initData and redirects', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555888',
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
