<?php

use App\Enums\OnboardingStep;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\StudyProgressReport;
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

it('builds strengths weaknesses and improvement insights for a student', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course);

    $resource = LearningResource::factory()->published()->quiz()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Kinship Quiz',
        'topics' => ['Kinship'],
    ]);

    $first = AssessmentAttempt::factory()->create([
        'user_id' => $user->id,
        'learning_resource_id' => $resource->id,
        'score' => 1,
        'total' => 2,
        'completed_at' => now()->subDays(3),
    ]);

    AssessmentAttemptAnswer::factory()->incorrect()->create([
        'assessment_attempt_id' => $first->id,
        'question_index' => 0,
        'question_key' => hash('sha256', 'q1'),
        'question_label' => 'What is kinship?',
        'topics' => ['Kinship'],
        'correct_index' => 1,
    ]);
    AssessmentAttemptAnswer::factory()->correct()->create([
        'assessment_attempt_id' => $first->id,
        'question_index' => 1,
        'question_key' => hash('sha256', 'q2'),
        'question_label' => 'What is culture?',
        'topics' => ['Kinship'],
        'correct_index' => 0,
    ]);

    $second = AssessmentAttempt::factory()->create([
        'user_id' => $user->id,
        'learning_resource_id' => $resource->id,
        'score' => 2,
        'total' => 2,
        'completed_at' => now()->subDay(),
    ]);

    AssessmentAttemptAnswer::factory()->correct()->create([
        'assessment_attempt_id' => $second->id,
        'question_index' => 0,
        'question_key' => hash('sha256', 'q1'),
        'question_label' => 'What is kinship?',
        'topics' => ['Kinship'],
        'correct_index' => 1,
    ]);
    AssessmentAttemptAnswer::factory()->correct()->create([
        'assessment_attempt_id' => $second->id,
        'question_index' => 1,
        'question_key' => hash('sha256', 'q2'),
        'question_label' => 'What is culture?',
        'topics' => ['Kinship'],
        'correct_index' => 0,
    ]);

    $report = app(StudyProgressReport::class)->forUser($user);

    expect($report['summary']['attempts'])->toBe(2)
        ->and($report['summary']['trend'])->toBe('improving')
        ->and($report['by_resource'][0]['title'])->toBe('Kinship Quiz')
        ->and($report['by_resource'][0]['improving'])->toBeTrue()
        ->and($report['focus'])->not->toBeEmpty();

    $this->actingAs($user)
        ->get(route('tg.progress'))
        ->assertOk()
        ->assertSee('Your study report', false)
        ->assertSee('Kinship Quiz', false)
        ->assertSee('improving', false);
});
