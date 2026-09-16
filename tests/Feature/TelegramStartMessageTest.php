<?php

use App\Enums\BroadcastButtonType;
use App\Enums\OnboardingStep;
use App\Enums\TelegramButtonStyle;
use App\Models\Course;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendPhoto')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['caption'] ?? ''), 'Welcome back, Abebe!')
            && str_contains((string) ($data['photo'] ?? ''), 'telegram/start/welcome.jpg')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === 'https://noviquni.test/tg/continue'
            && data_get($data, 'reply_markup.inline_keyboard.1.0.callback_data') === 'premium_pay';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && filled(data_get($request->data(), 'reply_markup.keyboard.0.0.web_app.url'));
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

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Welcome back, Abebe')
            && filled(data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url'));
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendPhoto'));
});
