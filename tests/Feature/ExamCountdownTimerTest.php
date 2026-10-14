<?php

use App\Enums\CollegeResourceKind;
use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\StudyRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('persists exam time limit minutes from the learning edit form', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Timed exam',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->assertFormSet([
            'type' => ResourceType::PracticeExams->value,
        ])
        ->fillForm([
            'exam_title' => 'Timed MCQ',
            'exam_instructions' => 'Finish before the clock ends.',
            'exam_time_limit_minutes' => 45,
            'quiz_questions' => [
                [
                    'question' => StudyRichText::forEditor('What is anthropology?'),
                    'options' => [
                        ['body' => StudyRichText::forEditor('A')],
                        ['body' => StudyRichText::forEditor('B')],
                        ['body' => StudyRichText::forEditor('C')],
                        ['body' => StudyRichText::forEditor('D')],
                    ],
                    'answerIndex' => 0,
                    'explanation' => StudyRichText::forEditor('Because.'),
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $resource->refresh();

    expect($resource->content['payload']['timeLimitMinutes'])->toBe(45)
        ->and($resource->playerPayload()['timeLimitMinutes'])->toBe(45);
});

it('renders a countdown timer when the exam has a time limit', function () {
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
        'type' => ResourceType::PracticeExams,
        'content' => [
            'kind' => CollegeResourceKind::Exam->value,
            'payload' => [
                'title' => 'Timed exam',
                'instructions' => 'Work quickly.',
                'timeLimitMinutes' => 90,
                'questions' => [
                    [
                        'question' => 'Which statement best describes anthropology?',
                        'options' => [
                            'It studies only fossils',
                            'It studies humankind across time and space',
                            'It is limited to genetics',
                            'It ignores culture',
                        ],
                        'answerIndex' => 1,
                        'explanation' => 'Anthropology aims for an integrated picture of humankind.',
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertSee('data-exam-timer', false)
        ->assertSee('timeLimitSeconds: 5400', false)
        ->assertSee('role="timer"', false);
});

it('omits the countdown timer when the exam has no time limit', function () {
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
    ]);

    expect($resource->playerPayload()['timeLimitMinutes'])->toBeNull();

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertDontSee('data-exam-timer', false)
        ->assertSee('Which statement best describes anthropology?', false);
});
