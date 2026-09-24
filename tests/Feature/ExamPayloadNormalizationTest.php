<?php

use App\Enums\CollegeResourceKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\College\TelegramResourceFormatter;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('normalizes nested college api exam payloads into a single mcq questions list', function () {
    $resource = LearningResource::factory()->exam()->create([
        'content' => [
            'kind' => CollegeResourceKind::Exam->value,
            'scope_type' => 'unit',
            'scope_id' => 'unit-1',
            'from_cache' => false,
            'generated_at' => now()->toIso8601String(),
            'payload' => [
                'instructions' => 'Attempt all parts.',
                'partA' => [
                    'title' => 'Part A — Multiple Choice',
                    'questions' => [
                        [
                            'question' => 'How many face-to-face study hours are allocated for Unit 1?',
                            'options' => ['2', '4', '6', '8'],
                            'answerIndex' => 1,
                            'explanation' => 'Study Hours: 4 face-to-face hours.',
                            'difficulty' => 'easy',
                        ],
                    ],
                ],
                'partB' => [
                    'title' => 'Part B — Short Answer',
                    'questions' => [
                        [
                            'question' => 'Analyze the primary pedagogical objectives.',
                            'marks' => 10,
                            'sampleAnswer' => 'Unit 1 is foundational.',
                            'rubric' => 'Award points for breadth.',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $payload = $resource->playerPayload();

    expect($payload)->not->toBeNull()
        ->and($payload['title'])->toBe('Part A — Multiple Choice')
        ->and($payload['instructions'])->toBe('Attempt all parts.')
        ->and($payload['questions'])->toHaveCount(1)
        ->and($payload['questions'][0]['question'])->toBe('How many face-to-face study hours are allocated for Unit 1?')
        ->and($payload)->not->toHaveKey('partA')
        ->and($payload)->not->toHaveKey('partB');
});

it('renders the exam player from nested college api exam content', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    $resource = LearningResource::factory()->published()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Unit 1 Exam',
        'type' => ResourceType::PracticeExams,
        'content' => [
            'kind' => CollegeResourceKind::Exam->value,
            'payload' => [
                'instructions' => 'Read all questions carefully. Time allowed: 90 minutes.',
                'partA' => [
                    'title' => 'Part A — Multiple Choice (20 marks)',
                    'questions' => [
                        [
                            'question' => 'How many face-to-face study hours are officially allocated for Unit 1 according to the course outline?',
                            'options' => [
                                '2 face-to-face hours',
                                '4 face-to-face hours',
                                '6 face-to-face hours',
                                '8 face-to-face hours',
                            ],
                            'answerIndex' => 1,
                            'explanation' => 'Study Hours: 4 face-to-face hours.',
                            'difficulty' => 'easy',
                        ],
                    ],
                ],
                'partB' => [
                    'questions' => [
                        [
                            'question' => 'Analyze the primary pedagogical objectives.',
                            'marks' => 10,
                            'sampleAnswer' => 'Unit 1 is foundational.',
                            'rubric' => 'Award points for breadth.',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertSee('Part A — Multiple Choice (20 marks)', false)
        ->assertSee('Read all questions carefully. Time allowed: 90 minutes.', false)
        ->assertSee('How many face-to-face study hours are officially allocated for Unit 1 according to the course outline?', false)
        ->assertDontSee('Analyze the primary pedagogical objectives.', false)
        ->assertDontSee('Part B — Short answer', false);
});

it('formats nested exam content for telegram without part b short answers', function () {
    $resource = LearningResource::factory()->exam()->create([
        'title' => 'Nested Exam',
        'content' => [
            'kind' => CollegeResourceKind::Exam->value,
            'payload' => [
                'partA' => [
                    'questions' => [
                        [
                            'question' => 'What is anthropology?',
                            'options' => ['A', 'B', 'C', 'D'],
                            'answerIndex' => 0,
                        ],
                    ],
                ],
                'partB' => [
                    'questions' => [
                        [
                            'question' => 'Write an essay about culture.',
                            'marks' => 10,
                            'sampleAnswer' => 'Culture is…',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $chunks = app(TelegramResourceFormatter::class)->format($resource);
    $body = implode("\n", $chunks);

    expect($body)->toContain('What is anthropology?')
        ->and($body)->not->toContain('Write an essay about culture.')
        ->and($body)->not->toContain('Part B');
});
