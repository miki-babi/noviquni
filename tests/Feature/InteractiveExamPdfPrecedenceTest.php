<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
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

/**
 * @return array{0: User, 1: Course, 2: Stream}
 */
function interactiveExamPdfContext(): array
{
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Maths',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    return [$user, $course, $stream];
}

it('hides pdf viewer when an interactive exam also has a telegram pdf attached', function () {
    [$user, $course, $stream] = interactiveExamPdfContext();

    $resource = LearningResource::factory()->published()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Interactive Exam With Stray Pdf',
        'telegram_files' => [
            [
                'file_id' => 'shared-pdf-file-id',
                'file_name' => 'Sat Practice Test With Answer Key.pdf',
            ],
        ],
    ]);

    expect($resource->playerPayload())->not->toBeNull()
        ->and($resource->hasPdfAttachments())->toBeTrue();

    $pdfUrl = route('tg.files.show', ['resource' => $resource, 'index' => 0], absolute: false);

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertSee('Which statement best describes anthropology?', false)
        ->assertDontSee('data-pdf-viewer-root', false)
        ->assertDontSee($pdfUrl, false)
        ->assertDontSee('Sat Practice Test With Answer Key.pdf', false);
});

it('still shows the pdf viewer for pdf-only practice exams', function () {
    [$user, $course, $stream] = interactiveExamPdfContext();

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::PracticeExams,
        'title' => 'Sat Practice Test With Answer Key',
        'generation_kind' => null,
        'content' => null,
        'telegram_files' => [
            [
                'file_id' => 'shared-pdf-file-id',
                'file_name' => 'Sat Practice Test With Answer Key.pdf',
            ],
        ],
    ]);

    expect($resource->playerPayload())->toBeNull()
        ->and($resource->hasPdfAttachments())->toBeTrue();

    $pdfUrl = route('tg.files.show', ['resource' => $resource, 'index' => 0], absolute: false);

    $this->actingAs($user)
        ->get(route('tg.play.exam', $resource))
        ->assertOk()
        ->assertSee('data-pdf-viewer-root', false)
        ->assertSee($pdfUrl, false)
        ->assertSee('Sat Practice Test With Answer Key.pdf', false);
});
