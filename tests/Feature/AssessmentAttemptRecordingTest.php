<?php

use App\Enums\AssessmentKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Enums\UserEventName;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Models\UserEvent;
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

it('records per-question quiz answers and server-side score', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id, 'name' => 'Anthropology']);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course);

    $resource = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'topics' => ['Kinship', 'Culture'],
    ]);

    $this->actingAs($user)
        ->postJson(route('tg.play.quiz.result', $resource), [
            'answers' => [
                0 => 1,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('score', 1)
        ->assertJsonPath('total', 1);

    $attempt = AssessmentAttempt::query()->where('user_id', $user->id)->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt->kind)->toBe(AssessmentKind::Quiz)
        ->and($attempt->score)->toBe(1)
        ->and($attempt->total)->toBe(1);

    $answer = AssessmentAttemptAnswer::query()->where('assessment_attempt_id', $attempt->id)->first();

    expect($answer)->not->toBeNull()
        ->and($answer->is_correct)->toBeTrue()
        ->and($answer->selected_index)->toBe(1)
        ->and($answer->topics)->toBe(['Kinship', 'Culture']);

    expect(UserEvent::query()->where('event', UserEventName::QuizDone)->exists())->toBeTrue();
});

it('records exam attempts including timed out partial answers', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course);

    $resource = LearningResource::factory()->published()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::PracticeExams,
    ]);

    $this->actingAs($user)
        ->postJson(route('tg.play.exam.result', $resource), [
            'answers' => [
                0 => 0,
            ],
            'timed_out' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('score', 0)
        ->assertJsonPath('total', 1);

    $attempt = AssessmentAttempt::query()->where('user_id', $user->id)->first();

    expect($attempt)->not->toBeNull()
        ->and($attempt->kind)->toBe(AssessmentKind::Exam)
        ->and($attempt->timed_out)->toBeTrue()
        ->and($attempt->score)->toBe(0);

    expect(UserEvent::query()->where('event', UserEventName::ExamDone)->exists())->toBeTrue();
});
