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

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'queue.default' => 'sync',
    ]);
});

it('sends generated quiz content as telegram messages instead of a document', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
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
        'type' => ResourceType::PracticeQuestion,
        'generation_kind' => CollegeResourceKind::Quiz,
        'is_premium' => false,
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
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555777,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 42,
                'chat' => ['id' => 555777],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request['text'] ?? '');

        return str_contains($text, 'Anthropology Quiz')
            && str_contains($text, 'What are the Greek roots of anthropology?')
            && str_contains($text, 'Anthropos and logos');
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});
