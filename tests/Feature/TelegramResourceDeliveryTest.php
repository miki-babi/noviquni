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
            && ($button['text'] ?? null) === 'Open in study app'
            && ($button['style'] ?? null) === 'success'
            && ($button['web_app']['url'] ?? null) === route('tg.play.quiz', $resource)
            && data_get($data, 'reply_markup.inline_keyboard.1.0.callback_data') === 'save:resource:'.$resource->id;
    });
});

it('opens premium mini app resources for free students so they can see the paywall', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555780',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_premium' => false,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->quiz()->premium()->create([
        'title' => 'Premium Deep Link Quiz',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'files' => null,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9004,
        'callback_query' => [
            'id' => 'cb-9004',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555780,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 45,
                'chat' => ['id' => 555780],
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

        return str_contains((string) ($data['text'] ?? ''), 'Premium Deep Link Quiz')
            && ($button['web_app']['url'] ?? null) === route('tg.play.quiz', $resource)
            && ! str_contains((string) ($data['text'] ?? ''), 'Premium Resource');
    });
});

it('opens pdf resources with a mini app player button instead of sending the document', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $relativePath = 'learnings/week-1-notes.pdf';
    Storage::disk($disk)->put($relativePath, 'fake-pdf-bytes');

    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 100]], 200),
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
        'type' => ResourceType::ReferenceBooks,
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
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $openButton = data_get($data, 'reply_markup.inline_keyboard.0.0', []);
        $saveShare = data_get($data, 'reply_markup.inline_keyboard.1', []);
        $shareUrl = (string) ($saveShare[1]['url'] ?? '');

        return str_contains((string) ($data['text'] ?? ''), 'Week 1 Lecture Notes')
            && ($openButton['text'] ?? null) === 'Open PDF player'
            && ($openButton['web_app']['url'] ?? null) === route('tg.play.reference-books', $resource)
            && ($saveShare[0]['callback_data'] ?? null) === 'save:resource:'.$resource->id
            && ($saveShare[0]['text'] ?? null) === 'Quick save'
            && str_contains($shareUrl, 't.me/share/url')
            && str_contains(urldecode($shareUrl), 'start=resource_'.$resource->id);
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});

it('shows a green Saved button when a pdf resource is already bookmarked', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 100]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555789',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Already Saved Notes',
        'description' => 'Lecture summary for week 2',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::ReferenceBooks,
        'is_premium' => false,
        'files' => null,
        'telegram_files' => [
            ['file_id' => 'BQACAgQAAxkBAAI-already-saved', 'file_name' => 'notes.pdf'],
        ],
    ]);

    Bookmark::query()->create([
        'user_id' => $user->id,
        'bookmarkable_type' => $resource->getMorphClass(),
        'bookmarkable_id' => $resource->id,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9009,
        'callback_query' => [
            'id' => 'cb-9009',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555789,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 59,
                'chat' => ['id' => 555789],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($resource) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $buttons = data_get($request->data(), 'reply_markup.inline_keyboard.1', []);
        $shareUrl = (string) ($buttons[1]['url'] ?? '');
        parse_str(parse_url($shareUrl, PHP_URL_QUERY) ?: '', $query);
        $shareText = (string) ($query['text'] ?? '');
        $deepLink = 'https://t.me/noviquni_bot?start=resource_'.$resource->id;
        $expectedShareText = "📚 Already Saved Notes\n\nLecture summary for week 2\n\nGet it here 👇\n{$deepLink}\n\n🎓 More freshman resources, exams, assignments, short notes & reference books are available on NOViQ Uni.";

        return data_get($request->data(), 'reply_markup.inline_keyboard.0.0.text') === 'Open PDF player'
            && ($buttons[0]['text'] ?? null) === 'Saved'
            && ($buttons[0]['style'] ?? null) === 'success'
            && ($buttons[0]['callback_data'] ?? null) === 'save:resource:'.$resource->id
            && str_contains($shareUrl, 't.me/share/url')
            && ($query['url'] ?? null) === $deepLink
            && $shareText === $expectedShareText;
    });
});

it('toggles a bookmark from the quick save callback and updates the button markup', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/editMessageReplyMarkup' => Http::response(['ok' => true, 'result' => ['message_id' => 60]], 200),
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
        'type' => ResourceType::ReferenceBooks,
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

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $resource)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageReplyMarkup')) {
            return false;
        }

        $button = data_get($request->data(), 'reply_markup.inline_keyboard.0.0', []);

        return ($button['text'] ?? null) === 'Saved'
            && ($button['style'] ?? null) === 'success';
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

    expect(Bookmark::query()->where('user_id', $user->id)->whereMorphedTo('bookmarkable', $resource)->exists())->toBeFalse();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageReplyMarkup')) {
            return false;
        }

        $button = data_get($request->data(), 'reply_markup.inline_keyboard.0.0', []);

        return ($button['text'] ?? null) === 'Quick save'
            && ! array_key_exists('style', $button);
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
        'type' => ResourceType::ReferenceBooks,
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

it('reports not found for missing resources on save', function () {
    Http::preventStrayRequests();
    Http::fake([
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
});
