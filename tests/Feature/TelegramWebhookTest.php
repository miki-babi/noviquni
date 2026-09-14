<?php

use App\Enums\OnboardingStep;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\Course;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'queue.default' => 'database',
    ]);
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

function telegramMessagePayload(int $telegramId, string $text, int $updateId = 1): array
{
    return [
        'update_id' => $updateId,
        'message' => [
            'message_id' => 10,
            'text' => $text,
            'chat' => ['id' => $telegramId],
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ];
}

function telegramCallbackPayload(int $telegramId, string $data, int $messageId = 20, int $updateId = 2): array
{
    return [
        'update_id' => $updateId,
        'callback_query' => [
            'id' => 'callback-'.$updateId,
            'data' => $data,
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => $messageId,
                'chat' => ['id' => $telegramId],
                'text' => 'previous',
            ],
        ],
    ];
}

it('accepts a start update and creates a student immediately without queueing', function () {
    Queue::fake();

    $stream = Stream::factory()->create(['name' => 'Natural']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555001, '/start'))
        ->assertOk();

    expect(User::query()->where('telegram_id', '555001')->exists())->toBeTrue();

    Queue::assertNotPushed(ProcessTelegramUpdateJob::class);

    Http::assertSent(function ($request) use ($stream) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Tap your stream')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "ob:stream:{$stream->id}";
    });
});

it('advances onboarding when a stream inline button is tapped', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    University::factory()->create(['name' => 'AAU']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555002, '/start'))->assertOk();

    $user = User::query()->where('telegram_id', '555002')->firstOrFail();
    expect($user->onboarding_step)->toBe(OnboardingStep::Stream);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555002, "ob:stream:{$stream->id}"))
        ->assertOk();

    $user->refresh();

    expect($user->stream_id)->toBe($stream->id)
        ->and($user->onboarding_step)->toBe(OnboardingStep::University);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'university')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'ob:uni:skip');
    });
});

it('completes button-only onboarding through skip and confirm', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);

    $telegramId = 555003;

    $this->postJson('/telegram/webhook', telegramMessagePayload($telegramId, '/start'))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload($telegramId, "ob:stream:{$stream->id}", 20, 2))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload($telegramId, 'ob:uni:skip', 20, 3))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload($telegramId, 'ob:sem:skip', 20, 4))->assertOk();
    $this->postJson('/telegram/webhook', telegramCallbackPayload($telegramId, 'ob:course:confirm', 20, 5))->assertOk();

    $user = User::query()->where('telegram_id', (string) $telegramId)->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Complete)
        ->and($user->courses()->pluck('courses.id')->all())->toContain($course->id);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Onboarding complete')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📚 My Courses';
    });
});

it('shows inline course buttons for My Courses after onboarding', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555004',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555004, '📚 My Courses'))
        ->assertOk();

    Http::assertSent(function ($request) use ($course) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your courses')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "course_resources:{$course->id}";
    });
});

it('reprompts with buttons when free text is sent during onboarding', function () {
    $stream = Stream::factory()->create(['name' => 'Social']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555005, '/start'))->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555005, 'Social', 2))->assertOk();

    $user = User::query()->where('telegram_id', '555005')->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Stream)
        ->and($user->stream_id)->toBeNull();

    Http::assertSent(function ($request) use ($stream) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "ob:stream:{$stream->id}";
    });
});
