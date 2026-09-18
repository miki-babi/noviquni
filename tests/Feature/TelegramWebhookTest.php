<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
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
        'app.url' => 'https://noviquni.test',
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
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📚 Courses'
            && data_get($data, 'reply_markup.keyboard.0.1.text') === '📖 Resources'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '🔖 Quick saved'
            && data_get($data, 'reply_markup.keyboard.2.0.text') === '👤 Profile'
            && data_get($data, 'reply_markup.keyboard.2.1.text') === '👥 Refer and earn'
            && data_get($data, 'reply_markup.keyboard.0.0.style') === 'success'
            && data_get($data, 'reply_markup.keyboard.0.1.style') === 'success'
            && data_get($data, 'reply_markup.keyboard.1.0.style') === 'primary'
            && data_get($data, 'reply_markup.keyboard.2.0.style') === 'primary'
            && data_get($data, 'reply_markup.keyboard.2.1.style') === 'primary'
            && data_get($data, 'reply_markup.keyboard.0.0.web_app') === null
            && data_get($data, 'reply_markup.keyboard.0.1.web_app') === null
            && data_get($data, 'reply_markup.keyboard.1.0.web_app') === null
            && data_get($data, 'reply_markup.keyboard.2.0.web_app') === null
            && data_get($data, 'reply_markup.keyboard.2.1.web_app') === null;
    });
});

it('shows setup CTA on start when enrolled user has no courses', function () {
    User::factory()->student()->create([
        'telegram_id' => '555020',
        'name' => 'Mikiyas Kebede',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555020, '/start', 400))->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'welcome to Noviq Uni')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'setup:courses'
            && data_get($data, 'reply_markup.inline_keyboard.0.0.text') === '🎯 Set up my courses';
    });
});

it('shows continue studying CTA on start when user has courses', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555021',
        'name' => 'Mikiyas',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555021, '/start', 401))->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Welcome back, Abebe')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.continue');
    });
});

it('shows enrolled courses in a reply keyboard when Courses is tapped', function () {
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

    $this->postJson('/telegram/webhook', telegramMessagePayload(555004, '📚 Courses'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your courses — tap one to study')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Physics'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back'
            && data_get($data, 'reply_markup.inline_keyboard') === null;
    });
});

it('restores the main reply keyboard when Back is tapped from courses', function () {
    User::factory()->student()->create([
        'telegram_id' => '555024',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555024, '← Back'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === '📚 Courses'
            && data_get($data, 'reply_markup.keyboard.0.1.text') === '📖 Resources';
    });
});

it('shows available resources in a reply keyboard when Resources is tapped', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Physics']);
    $user = User::factory()->student()->create([
        'telegram_id' => '555025',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Motion notes',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555025, '📖 Resources'))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Resources across your courses')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📄 Motion notes · Physics'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
    });
});

it('shows course setup for the legacy Browse label when no courses are enrolled', function () {
    User::factory()->student()->create([
        'telegram_id' => '555014',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555014, '📖 Browse', 500))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'setup:courses';
    });
});

it('shows coming soon browse state without counts when enrolled but empty', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555022',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555022, '📖 Browse', 402))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Your courses — tap one to study')
            && data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Mathematics'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
    });
});

it('shows study path after course tap', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555023',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $notes = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Week-1 notes',
    ]);
    $module = LearningResource::factory()->published()->module()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Module 1',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555023, "course:{$course->id}", 30, 403))
        ->assertOk();

    Http::assertSent(function ($request) use ($course, $notes, $module) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Study path for Physics')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "open_resource:{$notes->id}")
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "open_resource:{$module->id}")
            && $buttons->contains(fn (array $button) => ($button['web_app']['url'] ?? null) === route('tg.courses.show', $course));
    });
});

it('shows continue resume card when a prior download exists', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555024',
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
    $user->downloads()->create(['learning_resource_id' => $resource->id]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555024, '📚 Continue', 404))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Pick up where you left off')
            && str_contains((string) ($data['text'] ?? ''), 'Mathematics')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'continue:resume')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'continue:switch');
    });
});

it('shows profile submenu with notifications refer and settings', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555025',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555025, '👤 Profile', 405))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return str_contains((string) ($data['text'] ?? ''), 'Open Profile in the study app')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.web_app.url') === route('tg.profile')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.text') === '👤 Open Profile';
    });
});

it('switches language and refreshes keyboard labels', function () {
    User::factory()->student()->create([
        'telegram_id' => '555026',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'telegram_locale' => 'en',
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555026, 'settings:lang:am', 30, 406))
        ->assertOk();

    $user = User::query()->where('telegram_id', '555026')->firstOrFail();
    expect($user->telegram_locale)->toBe('am');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === '📚 ኮርሶች'
            && data_get($data, 'reply_markup.keyboard.0.1.text') === '📖 መርጃዎች'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '🔖 በፍጥነት የተቀመጡ'
            && data_get($data, 'reply_markup.keyboard.2.1.text') === '👥 ይጋብዙና ያግኙ';
    });
});

it('still routes the legacy My Courses label to the course reply keyboard', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Chemistry']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555027',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555027, '📚 My Courses', 407))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return data_get($data, 'reply_markup.keyboard.0.0.text') === '📗 Chemistry'
            && data_get($data, 'reply_markup.keyboard.1.0.text') === '← Back';
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

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555010, 'back:courses', 30, 103))
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/editMessageText')
            && str_contains((string) data_get($request->data(), 'text'), 'Your courses — tap one to study');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && str_contains((string) data_get($request->data(), 'text'), 'Your courses — tap one to study');
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

it('opens a bait course path from /start bait', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
        'slug' => 'chemistry',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555030',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $notes = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Bait notes',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555030, '/start bait', 500))
        ->assertOk();

    Http::assertSent(function ($request) use ($notes) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Study path for Chemistry')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === "open_resource:{$notes->id}");
    });
});

it('opens a resource from /start resource deep link', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555031',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Deep link notes',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555031, '/start resource_'.$resource->id, 501))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Deep link notes');
    });
});

it('opens a course from /start course deep link', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'English',
        'slug' => 'english',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555032',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'English bait',
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555032, '/start course_english', 502))
        ->assertOk();

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Study path for English');
    });
});

it('opens referrals from the refer and earn reply keyboard label', function () {
    $user = User::factory()->student()->create([
        'telegram_id' => '555033',
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555033, '👥 Refer and earn', 503))
        ->assertOk();

    Http::assertSent(function ($request) use ($user) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) data_get($request->data(), 'text');

        return str_contains($text, 'Refer & Earn')
            && str_contains($text, $user->referral_code);
    });
});

it('still opens continue from the legacy continue label', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Biology']);

    $user = User::factory()->student()->create([
        'telegram_id' => '555034',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Legacy continue notes',
    ]);
    $user->downloads()->create([
        'learning_resource_id' => $resource->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555034, '📚 Continue', 504))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) data_get($request->data(), 'text'), 'Pick up where you left off');
    });
});

it('lists quick saved as chat buttons newest first without opening the Mini App', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555040',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $older = LearningResource::factory()->bait()->notes()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Older notes',
        'type' => ResourceType::LectureNotes,
    ]);
    $newer = LearningResource::factory()->bait()->worksheet()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Newer worksheet',
        'type' => ResourceType::Worksheet,
    ]);

    Bookmark::query()->create([
        'user_id' => $user->id,
        'learning_resource_id' => $older->id,
    ]);
    Bookmark::query()->create([
        'user_id' => $user->id,
        'learning_resource_id' => $newer->id,
    ]);

    $this->postJson('/telegram/webhook', telegramMessagePayload(555040, '🔖 Quick saved', 540))
        ->assertOk();

    Http::assertSent(function ($request) use ($older, $newer) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $rows = collect(data_get($data, 'reply_markup.inline_keyboard', []));
        $buttons = $rows->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (1/1)')
            && data_get($rows, '0.0.callback_data') === "open_resource:{$newer->id}"
            && data_get($rows, '1.0.callback_data') === "open_resource:{$older->id}"
            && $buttons->contains(fn (array $button) => str_contains((string) ($button['text'] ?? ''), 'Worksheet · Newer worksheet'))
            && $buttons->contains(fn (array $button) => str_contains((string) ($button['text'] ?? ''), 'Lecture notes · Older notes'))
            && $buttons->every(fn (array $button) => ! isset($button['web_app']));
    });
});

it('paginates quick saved with next and back callbacks', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Chemistry',
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555041',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    foreach (range(1, 6) as $index) {
        $resource = LearningResource::factory()->bait()->notes()->create([
            'course_id' => $course->id,
            'stream_id' => $stream->id,
            'title' => "Saved notes {$index}",
        ]);

        Bookmark::query()->create([
            'user_id' => $user->id,
            'learning_resource_id' => $resource->id,
        ]);
    }

    $this->postJson('/telegram/webhook', telegramMessagePayload(555041, '🔖 Quick saved', 541))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $rows = collect(data_get($data, 'reply_markup.inline_keyboard', []));
        $buttons = $rows->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (1/2)')
            && $rows->count() === 6
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'saved:page:1'
                && ($button['text'] ?? '') === 'Next ›')
            && $buttons->every(fn (array $button) => ($button['callback_data'] ?? '') !== 'saved:page:0');
    });

    $this->postJson('/telegram/webhook', telegramCallbackPayload(555041, 'saved:page:1', 99, 542))
        ->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $buttons = collect(data_get($data, 'reply_markup.inline_keyboard', []))->flatten(1);

        return str_contains((string) ($data['text'] ?? ''), 'Quick saved (2/2)')
            && $buttons->contains(fn (array $button) => ($button['callback_data'] ?? '') === 'saved:page:0'
                && ($button['text'] ?? '') === '‹ Back')
            && $buttons->every(fn (array $button) => ($button['callback_data'] ?? '') !== 'saved:page:1');
    });
});
