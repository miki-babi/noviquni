<?php

use App\Enums\CollegeResourceKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\LearningResource;
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
        'services.telegram.bot_username' => 'noviquni_bot',
        'queue.default' => 'sync',
    ]);
});

it('lists generated quiz resources as direct mini app buttons', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555777',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Anthropology Quiz',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Quiz,
        'generation_kind' => CollegeResourceKind::Quiz,
        'is_premium' => false,
        'files' => null,
        'content' => [
            'kind' => 'quiz',
            'scope_type' => 'section',
            'scope_id' => 'section-1',
            'from_cache' => false,
            'generated_at' => '2026-09-15T12:46:32.167Z',
            'payload' => [
                'questions' => [
                    [
                        'question' => 'What are the Greek roots of anthropology?',
                        'options' => [
                            'Wrong A',
                            'Anthropos and logos',
                            'Wrong C',
                            'Wrong D',
                        ],
                        'answerIndex' => 1,
                        'explanation' => 'Anthropos means human.',
                        'difficulty' => 'easy',
                    ],
                ],
            ],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9001,
        'callback_query' => [
            'id' => 'cb-9001',
            'data' => "rtype:quiz:{$course->id}",
            'from' => [
                'id' => 555777,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 42,
                'chat' => ['id' => 555777],
                'text' => 'Choose a type',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $button = data_get($data, 'reply_markup.inline_keyboard.0.0', []);

        return ($button['text'] ?? null) === 'Anthropology Quiz'
            && ($button['style'] ?? null) === 'success'
            && ($button['web_app']['url'] ?? null) === route('tg.play.quiz', $resource)
            && ! array_key_exists('callback_data', $button)
            && str_contains((string) ($data['text'] ?? ''), (string) $resource->course?->name)
            && str_contains((string) ($data['text'] ?? ''), 'Quiz');
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});

it('opens a non-file resource deep link with a mini app title button', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555779',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->quiz()->create([
        'title' => 'Deep Link Quiz',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'is_premium' => false,
        'files' => null,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9003,
        'callback_query' => [
            'id' => 'cb-9003',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555779,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 44,
                'chat' => ['id' => 555779],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $button = data_get($data, 'reply_markup.inline_keyboard.0.0', []);

        return str_contains((string) ($data['text'] ?? ''), 'Deep Link Quiz')
            && ($button['text'] ?? null) === 'Deep Link Quiz'
            && ($button['style'] ?? null) === 'success'
            && ($button['web_app']['url'] ?? null) === route('tg.play.quiz', $resource);
    });
});

it('sends uploaded resource files as telegram documents', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $relativePath = 'learnings/week-1-notes.pdf';
    Storage::disk($disk)->put($relativePath, 'fake-pdf-bytes');

    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 100]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555778',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Week 1 Lecture Notes',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'generation_kind' => null,
        'content' => null,
        'is_premium' => false,
        'files' => [$relativePath],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9002,
        'callback_query' => [
            'id' => 'cb-9002',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555778,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 43,
                'chat' => ['id' => 555778],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/sendDocument')) {
            return false;
        }

        $data = $request->data();
        $markup = $data['reply_markup'] ?? null;

        // Multipart uploads expose fields as [{name, contents}, ...]
        if ($markup === null && array_is_list($data)) {
            foreach ($data as $field) {
                if (($field['name'] ?? null) === 'reply_markup') {
                    $markup = $field['contents'] ?? null;
                    break;
                }
            }
        }

        if (is_string($markup)) {
            $markup = json_decode($markup, true);
        }

        $buttons = data_get($markup, 'inline_keyboard.0', []);

        return ($buttons[0]['callback_data'] ?? null) === 'save:resource:'.$resource->id
            && ($buttons[1]['callback_data'] ?? null) === 'share:resource:'.$resource->id;
    });

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $button = data_get($request->data(), 'reply_markup.inline_keyboard.0.0', []);

        return array_key_exists('web_app', $button);
    });
});

it('toggles a bookmark from the quick save callback', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555790',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Saveable Notes',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'is_premium' => false,
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-saveable', 'file_name' => 'notes.pdf'],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9010,
        'callback_query' => [
            'id' => 'cb-9010',
            'data' => 'save:resource:'.$resource->id,
            'from' => [
                'id' => 555790,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 60,
                'chat' => ['id' => 555790],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->where('learning_resource_id', $resource->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'Saved');
    });

    $this->postJson('/telegram/webhook', [
        'update_id' => 9011,
        'callback_query' => [
            'id' => 'cb-9011',
            'data' => 'save:resource:'.$resource->id,
            'from' => [
                'id' => 555790,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 60,
                'chat' => ['id' => 555790],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->where('learning_resource_id', $resource->id)->exists())->toBeFalse();
});

it('sends a share deep link message for a resource file', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 70]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555791',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Shareable Notes',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'is_premium' => false,
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-shareable', 'file_name' => 'notes.pdf'],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9012,
        'callback_query' => [
            'id' => 'cb-9012',
            'data' => 'share:resource:'.$resource->id,
            'from' => [
                'id' => 555791,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 61,
                'chat' => ['id' => 555791],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return str_contains($text, 'Shareable Notes')
            && str_contains($text, '?start=resource_'.$resource->id);
    });
});

it('blocks quick save when onboarding is incomplete', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555792',
        'onboarding_step' => OnboardingStep::Stream,
    ]);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Locked Notes',
        'type' => ResourceType::Notes,
        'is_premium' => false,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9013,
        'callback_query' => [
            'id' => 'cb-9013',
            'data' => 'save:resource:'.$resource->id,
            'from' => [
                'id' => 555792,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 62,
                'chat' => ['id' => 555792],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    expect(Bookmark::query()->where('user_id', $user->id)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        return filled($request->data()['text'] ?? null);
    });
});

it('reports not found for missing resources on save and share', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 71]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555793',
        'onboarding_step' => OnboardingStep::Complete,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9014,
        'callback_query' => [
            'id' => 'cb-9014',
            'data' => 'save:resource:999999',
            'from' => [
                'id' => 555793,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 63,
                'chat' => ['id' => 555793],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'not found');
    });

    $this->postJson('/telegram/webhook', [
        'update_id' => 9015,
        'callback_query' => [
            'id' => 'cb-9015',
            'data' => 'share:resource:999999',
            'from' => [
                'id' => 555793,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 64,
                'chat' => ['id' => 555793],
                'text' => 'file',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'not found');
    });
});
