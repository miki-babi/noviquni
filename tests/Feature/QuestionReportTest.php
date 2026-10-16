<?php

use App\Enums\OnboardingStep;
use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use App\Enums\UserRole;
use App\Filament\Resources\QuestionReports\Pages\ViewQuestionReport;
use App\Models\DailyChallenge;
use App\Models\LearningResource;
use App\Models\QuestionReport;
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

it('lets an authenticated student report a quiz question', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'role' => UserRole::Student,
    ]);
    $resource = LearningResource::factory()->published()->quiz()->create();

    $this->actingAs($user)
        ->postJson(route('tg.play.question-report'), [
            'source' => QuestionReportSource::Quiz->value,
            'learning_resource_id' => $resource->id,
            'question_index' => 0,
            'reason' => QuestionReportReason::Typo->value,
            'note' => 'Small typo in the stem',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $report = QuestionReport::query()->first();

    expect($report)->not->toBeNull()
        ->and($report->user_id)->toBe($user->id)
        ->and($report->learning_resource_id)->toBe($resource->id)
        ->and($report->source)->toBe(QuestionReportSource::Quiz)
        ->and($report->reason)->toBe(QuestionReportReason::Typo)
        ->and($report->status)->toBe(QuestionReportStatus::Pending)
        ->and($report->question_label)->not->toBeEmpty();
});

it('lets an authenticated student report a daily challenge question', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'role' => UserRole::Student,
    ]);
    $challenge = DailyChallenge::factory()->create();

    $this->actingAs($user)
        ->postJson(route('tg.play.question-report'), [
            'source' => QuestionReportSource::DailyChallenge->value,
            'daily_challenge_id' => $challenge->id,
            'question_index' => 0,
            'reason' => QuestionReportReason::Unclear->value,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(QuestionReport::query()->where('daily_challenge_id', $challenge->id)->count())->toBe(1);
});

it('requires a description when the reason is other', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    $resource = LearningResource::factory()->published()->quiz()->create();

    $this->actingAs($user)
        ->postJson(route('tg.play.question-report'), [
            'source' => QuestionReportSource::Quiz->value,
            'learning_resource_id' => $resource->id,
            'question_index' => 0,
            'reason' => QuestionReportReason::Other->value,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);

    $this->actingAs($user)
        ->postJson(route('tg.play.question-report'), [
            'source' => QuestionReportSource::Quiz->value,
            'learning_resource_id' => $resource->id,
            'question_index' => 0,
            'reason' => QuestionReportReason::Other->value,
            'note' => 'The options do not match the stem',
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(QuestionReport::query()->where('reason', QuestionReportReason::Other)->count())->toBe(1);
});

it('rejects a second pending report for the same question', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    $resource = LearningResource::factory()->published()->quiz()->create();

    QuestionReport::factory()->create([
        'user_id' => $user->id,
        'learning_resource_id' => $resource->id,
        'question_index' => 0,
        'status' => QuestionReportStatus::Pending,
    ]);

    $this->actingAs($user)
        ->postJson(route('tg.play.question-report'), [
            'source' => QuestionReportSource::Quiz->value,
            'learning_resource_id' => $resource->id,
            'question_index' => 0,
            'reason' => QuestionReportReason::WrongAnswer->value,
        ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'You already reported this question.');

    expect(QuestionReport::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('returns 401 json for unauthenticated report requests', function () {
    $resource = LearningResource::factory()->published()->quiz()->create();

    $this->postJson(route('tg.play.question-report'), [
        'source' => QuestionReportSource::Quiz->value,
        'learning_resource_id' => $resource->id,
        'question_index' => 0,
        'reason' => QuestionReportReason::Typo->value,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Telegram Mini App authentication required.');
});

it('lets an admin resolve and dismiss question reports from filament', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $student = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    $resource = LearningResource::factory()->published()->quiz()->create([
        'title' => 'Reported Quiz',
    ]);

    $resolveTarget = QuestionReport::factory()->create([
        'user_id' => $student->id,
        'learning_resource_id' => $resource->id,
        'question_label' => 'Resolve me',
    ]);
    $dismissTarget = QuestionReport::factory()->create([
        'user_id' => $student->id,
        'learning_resource_id' => $resource->id,
        'question_label' => 'Dismiss me',
        'question_index' => 1,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewQuestionReport::class, ['record' => $resolveTarget->getRouteKey()])
        ->callAction('resolve', [
            'admin_notes' => 'Fixed the typo',
        ])
        ->assertNotified();

    $resolveTarget->refresh();

    expect($resolveTarget->status)->toBe(QuestionReportStatus::Resolved)
        ->and($resolveTarget->resolved_by)->toBe($admin->id)
        ->and($resolveTarget->resolved_at)->not->toBeNull()
        ->and($resolveTarget->admin_notes)->toBe('Fixed the typo');

    Livewire::test(ViewQuestionReport::class, ['record' => $dismissTarget->getRouteKey()])
        ->callAction('dismiss', [
            'admin_notes' => 'Not actionable',
        ])
        ->assertNotified();

    $dismissTarget->refresh();

    expect($dismissTarget->status)->toBe(QuestionReportStatus::Dismissed)
        ->and($dismissTarget->resolved_by)->toBe($admin->id)
        ->and($dismissTarget->admin_notes)->toBe('Not actionable');
});

it('lets an admin edit the reported question from filament and mark it resolved', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $student = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
    ]);
    $resource = LearningResource::factory()->published()->quiz()->create([
        'title' => 'Editable Quiz',
    ]);

    $report = QuestionReport::factory()->create([
        'user_id' => $student->id,
        'learning_resource_id' => $resource->id,
        'question_index' => 0,
        'question_label' => 'What are the Greek roots of anthropology?',
        'reason' => QuestionReportReason::Typo,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewQuestionReport::class, ['record' => $report->getRouteKey()])
        ->callAction('editQuestion', [
            'question' => StudyRichText::forEditor('What are the corrected Greek roots of anthropology?'),
            'options' => [
                ['body' => StudyRichText::forEditor('Wrong A')],
                ['body' => StudyRichText::forEditor('Anthropos and logos')],
                ['body' => StudyRichText::forEditor('Wrong C')],
                ['body' => StudyRichText::forEditor('Wrong D')],
            ],
            'answerIndex' => 1,
            'explanation' => StudyRichText::forEditor('Updated explanation.'),
            'mark_resolved' => true,
            'admin_notes' => 'Fixed the stem typo',
        ])
        ->assertNotified();

    $resource->refresh();
    $report->refresh();

    $question = $resource->playerPayload()['questions'][0];

    expect(StudyRichText::toPlain($question['question']))->toContain('corrected Greek roots')
        ->and(StudyRichText::toPlain($question['explanation'] ?? null))->toContain('Updated explanation')
        ->and($question['answerIndex'])->toBe(1)
        ->and($report->status)->toBe(QuestionReportStatus::Resolved)
        ->and($report->resolved_by)->toBe($admin->id)
        ->and($report->admin_notes)->toBe('Fixed the stem typo')
        ->and($report->question_label)->toContain('corrected Greek roots');
});
