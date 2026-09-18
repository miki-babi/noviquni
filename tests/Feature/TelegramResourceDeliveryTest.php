<?php

use App\Enums\CollegeResourceKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
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
        'is_bait' => true,
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
        'is_bait' => true,
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
        'is_bait' => true,
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

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendDocument');
    });

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $button = data_get($request->data(), 'reply_markup.inline_keyboard.0.0', []);

        return array_key_exists('web_app', $button);
    });
});
