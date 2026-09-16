<?php

use App\Enums\OnboardingStep;
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
    ]);
    Storage::fake('public');
    Http::fake([
        'api.telegram.org/bot*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 50]], 200),
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 51]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('sends start captions with telegram-safe rich text formatting', function () {
    Storage::disk('public')->put('telegram/start/welcome.jpg', 'fake-image');

    $settings = app(SettingsService::class);
    $settings->set(SettingsService::TELEGRAM_START_IMAGE, 'telegram/start/welcome.jpg');
    $settings->set(
        SettingsService::TELEGRAM_START_CAPTION,
        '<p>Welcome back, <strong>{{first_name}}</strong>!</p><p>Keep going.</p>',
    );

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => '555910',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9101,
        'message' => [
            'message_id' => 10,
            'text' => '/start',
            'chat' => ['id' => 555910],
            'from' => [
                'id' => 555910,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendPhoto')) {
            return false;
        }

        $fields = collect($request->data())
            ->mapWithKeys(fn (array $part) => [$part['name'] => $part['contents']]);

        return $fields->get('caption') === "Welcome back, <b>Abebe</b>!\nKeep going."
            && $fields->get('parse_mode') === 'HTML';
    });
});
