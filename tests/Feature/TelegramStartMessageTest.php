<?php

use App\Enums\BroadcastButtonType;
use App\Enums\OnboardingStep;
use App\Enums\TelegramButtonStyle;
use App\Models\Course;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'queue.default' => 'sync',
        'app.url' => 'https://noviquni.test',
        'filesystems.disks.public.url' => 'https://noviquni.test/storage',
    ]);
    Storage::fake('public');
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 50]], 200),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 51]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

function telegramStartPayload(int $telegramId, int $updateId = 1): array
{
    return [
        'update_id' => $updateId,
        'message' => [
            'message_id' => 10,
            'text' => '/start',
            'chat' => ['id' => $telegramId],
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ];
}

it('sends the custom start message to new users before the stream onboarding prompt', function () {
    Storage::disk('public')->put('telegram/start/welcome.jpg', 'fake-image');

    $settings = app(SettingsService::class);
    $settings->set(SettingsService::TELEGRAM_START_IMAGE, 'telegram/start/welcome.jpg');
    $settings->set(SettingsService::TELEGRAM_START_CAPTION, 'Welcome, {{first_name}}!');
    $settings->set(SettingsService::TELEGRAM_START_BUTTONS, json_encode([
        [
            'label' => 'Learn more',
            'type' => BroadcastButtonType::Url->value,
            'url' => 'https://noviquni.test/about',
            'style' => TelegramButtonStyle::Primary->value,
        ],
    ], JSON_THROW_ON_ERROR));

    $stream = Stream::factory()->create(['name' => 'Natural']);

    $this->postJson('/telegram/webhook', telegramStartPayload(555910, 9010))->assertOk();

    $user = User::query()->where('telegram_id', '555910')->firstOrFail();
    expect($user->onboarding_step)->toBe(OnboardingStep::Stream);

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendPhoto')) {
            return false;
        }

        $fields = collect($request->data())
            ->mapWithKeys(fn (array $part) => [$part['name'] => $part['contents']]);

        $markup = json_decode((string) $fields->get('reply_markup'), true);

        $caption = (string) $fields->get('caption');

        return str_contains($caption, 'Welcome, Abebe!')
            && str_contains($caption, '<a href="https://t.me/')
            && str_contains($caption, 'Start now</a>')
            && str_contains($caption, 'start='.$user->referral_code)
            && data_get($markup, 'inline_keyboard.0.0.url') === 'https://noviquni.test/about';
    });

    Http::assertSent(function ($request) use ($stream) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Tap your stream')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "ob:stream:{$stream->id}";
    });

    Http::assertNotSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/sendMessage')
            && (
                str_contains((string) ($data['text'] ?? ''), 'Your study menu is ready')
                || filled(data_get($data, 'reply_markup.keyboard'))
            );
    });
});

it('sends the admin start photo caption and inline buttons on /start', function () {
    Storage::disk('public')->put('telegram/start/welcome.jpg', 'fake-image');

    $settings = app(SettingsService::class);
    $settings->set(SettingsService::TELEGRAM_START_IMAGE, 'telegram/start/welcome.jpg');
    $settings->set(SettingsService::TELEGRAM_START_CAPTION, 'Welcome back, {{first_name}}!');
    $settings->set(SettingsService::TELEGRAM_START_BUTTONS, json_encode([
        [
            'label' => 'Continue studying',
            'type' => BroadcastButtonType::MiniApp->value,
            'url' => 'https://noviquni.test/tg/continue',
            'style' => TelegramButtonStyle::Primary->value,
        ],
        [
            'label' => 'Premium',
            'type' => BroadcastButtonType::Command->value,
            'command' => 'premium_pay',
            'style' => TelegramButtonStyle::Success->value,
        ],
    ], JSON_THROW_ON_ERROR));

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555900',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramStartPayload(555900, 9001))->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendPhoto')) {
            return false;
        }

        $fields = collect($request->data())
            ->mapWithKeys(fn (array $part) => [$part['name'] => $part['contents']]);

        $markup = json_decode((string) $fields->get('reply_markup'), true);
        $caption = (string) $fields->get('caption');

        return str_contains($caption, 'Welcome back, Abebe!')
            && str_contains($caption, 'Start now</a>')
            && str_contains($caption, 'start='.$user->referral_code)
            && data_get($markup, 'inline_keyboard.0.0.web_app.url') === 'https://noviquni.test/tg/continue'
            && data_get($markup, 'inline_keyboard.1.0.callback_data') === 'premium_pay'
            && collect($request->data())->contains(fn (array $part) => ($part['name'] ?? null) === 'photo'
                && ($part['filename'] ?? null) === 'welcome.jpg');
    });

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) ($data['text'] ?? ''), 'Your study menu is ready')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📖 Resources'
            && data_get($data, 'reply_markup.keyboard.0.0.web_app') === null;
    });
});

it('falls back to default welcome when no custom start message is configured', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555901',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramStartPayload(555901, 9002))->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Welcome back, Abebe')
            && str_contains($text, 'Start now</a>')
            && str_contains($text, 'start='.$user->referral_code)
            && filled(data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url'));
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendPhoto'));
});

it('logs the outbound telegram payloads sent after /start', function () {
    $logged = collect();

    Event::listen(
        MessageLogged::class,
        function (MessageLogged $event) use ($logged): void {
            $logged->push($event);
        }
    );

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555902',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramStartPayload(555902, 9003))->assertOk();

    $startLogs = $logged
        ->filter(fn (MessageLogged $event): bool => $event->level === 'info'
            && $event->message === 'Telegram start message sent')
        ->values();

    expect($startLogs)->toHaveCount(2)
        ->and($startLogs[0]->context['method'] ?? null)->toBe('sendMessage')
        ->and($startLogs[0]->context['chat_id'] ?? null)->toBe(555902)
        ->and($startLogs[0]->context['ok'] ?? null)->toBeTrue()
        ->and($startLogs[0]->context['telegram_message_id'] ?? null)->toBe(51)
        ->and((string) ($startLogs[0]->context['text'] ?? ''))->toContain('Welcome back, Abebe')
        ->and(data_get($startLogs[0]->context, 'reply_markup.inline_keyboard.0.0.web_app.url'))->toBe(route('tg.browse'))
        ->and((string) ($startLogs[1]->context['text'] ?? ''))->toContain('Your study menu is ready')
        ->and(data_get($startLogs[1]->context, 'reply_markup.keyboard.0.0.text'))->toBe('📖 Resources');
});

it('logs the start photo caption when a custom start image is sent', function () {
    $logged = collect();

    Event::listen(
        MessageLogged::class,
        function (MessageLogged $event) use ($logged): void {
            $logged->push($event);
        }
    );

    Storage::disk('public')->put('telegram/start/welcome.jpg', 'fake-image');

    $settings = app(SettingsService::class);
    $settings->set(SettingsService::TELEGRAM_START_IMAGE, 'telegram/start/welcome.jpg');
    $settings->set(SettingsService::TELEGRAM_START_CAPTION, 'Welcome back, {{first_name}}!');

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555903',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramStartPayload(555903, 9004))->assertOk();

    $photoLog = $logged->first(
        fn (MessageLogged $event): bool => $event->level === 'info'
            && $event->message === 'Telegram start message sent'
            && ($event->context['method'] ?? null) === 'sendPhoto'
    );

    expect($photoLog)->not->toBeNull()
        ->and($photoLog->context['caption'] ?? null)->toContain('Welcome back, Abebe!')
        ->and($photoLog->context['caption'] ?? null)->toContain('Start now</a>')
        ->and($photoLog->context['caption'] ?? null)->toContain('start='.$user->referral_code)
        ->and($photoLog->context['file_name'] ?? null)->toBe('welcome.jpg')
        ->and($photoLog->context['ok'] ?? null)->toBeTrue()
        ->and($photoLog->context['telegram_message_id'] ?? null)->toBe(50);
});

it('does not log start payloads for other telegram commands', function () {
    $logged = collect();

    Event::listen(
        MessageLogged::class,
        function (MessageLogged $event) use ($logged): void {
            $logged->push($event);
        }
    );

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'telegram_id' => '555904',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9005,
        'message' => [
            'message_id' => 10,
            'text' => '👤 Profile',
            'chat' => ['id' => 555904],
            'from' => [
                'id' => 555904,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ])->assertOk();

    expect($logged->contains(
        fn (MessageLogged $event): bool => $event->message === 'Telegram start message sent'
    ))->toBeFalse();
});
