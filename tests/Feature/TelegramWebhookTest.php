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
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
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
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "ob:stream:{$stream->id}"
            && data_get($data, 'reply_markup.inline_keyboard.0.0.style') === 'primary';
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
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'ob:uni:skip'
                && ($button['style'] ?? null) === 'danger');
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
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📚 My Courses'
            && data_get($data, 'reply_markup.keyboard.0.0.style') === 'primary'
            && data_get($data, 'reply_markup.keyboard.1.0.style') === 'success'
            && ! array_key_exists('style', data_get($data, 'reply_markup.keyboard.2.0', []));
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
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === "course_resources:{$course->id}"
            && data_get($data, 'reply_markup.inline_keyboard.0.0.style') === 'primary';
    });
});

it('reprompts with buttons when free text is sent during onboarding', function () {
    $stream = Stream::factory()->create(['name' => 'Social']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555005, '/start'))->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555005, 'Social', 2))->assertOk();

    $user = User::query()->where('telegram_id', '555005')->firstOrFail();

    expect($user->onboarding_step)->toBe(OnboardingStep::Stream)
        ->and($user->stream_id)->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/editMessageText')
            && data_get($request->data(), 'message_id') === 99;
    });
});

it('does not send duplicate university prompts for stale stream callbacks', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555006',
        'onboarding_step' => OnboardingStep::University,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $sendCountBefore = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/sendMessage'))
        ->count();

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555006, "ob:stream:{$stream->id}", 20, 100))
        ->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::University);

    $newUniversitySends = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/sendMessage')
            && str_contains((string) data_get($record[0]->data(), 'text'), 'university'))
        ->count();

    expect($newUniversitySends)->toBe($sendCountBefore);
});

it('does not send duplicate semester prompts for stale university callbacks', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555007',
        'onboarding_step' => OnboardingStep::Courses,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555007, 'ob:uni:skip', 20, 101))
        ->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::Courses);

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'semester');
    });
});

it('does not resend onboarding complete message for stale confirm callbacks', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Math']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555008',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555008, 'ob:course:confirm', 20, 102))
        ->assertOk();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'Onboarding complete');
    });
});

it('ignores duplicate update ids', function () {
    $stream = Stream::factory()->create(['name' => 'Natural']);
    University::factory()->create(['name' => 'AAU']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555009, '/start', 200))->assertOk();

    $user = User::query()->where('telegram_id', '555009')->firstOrFail();
    expect($user->onboarding_step)->toBe(OnboardingStep::Stream);

    $editCountBefore = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/editMessageText'))
        ->count();

    $payload = telegramCallbackPayload(555009, "ob:stream:{$stream->id}", 20, 201);

    $this->postJson('/telegram/webhook', $payload)->assertOk();
    $this->postJson('/telegram/webhook', $payload)->assertOk();

    $user->refresh();

    expect($user->onboarding_step)->toBe(OnboardingStep::University);

    $newEdits = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/editMessageText'))
        ->count() - $editCountBefore;

    expect($newEdits)->toBe(1);
});

it('uses editMessageText for back to courses navigation', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Biology']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555010',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555010, 'back:courses', 30, 103))
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/editMessageText')
            && str_contains((string) data_get($request->data(), 'text'), 'Pick a course');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'Pick a course');
    });
});

it('rate limits free text reprompts during onboarding', function () {
    $stream = Stream::factory()->create(['name' => 'Social']);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555011, '/start', 300))->assertOk();

    $editCountAfterStart = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/editMessageText'))
        ->count();

    $this->postJson('/telegram/webhook', telegramMessagePayload(555011, 'hello', 301))->assertOk();
    $this->postJson('/telegram/webhook', telegramMessagePayload(555011, 'hello again', 302))->assertOk();

    $editCountAfterReprompts = collect(Http::recorded())
        ->filter(fn (array $record) => str_contains($record[0]->url(), '/editMessageText'))
        ->count();

    expect($editCountAfterReprompts - $editCountAfterStart)->toBe(1);
});
