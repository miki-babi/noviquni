<?php

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Support\LearningResourceFiles;
use App\Support\StudyRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves edited quiz questions from the learning edit builder', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Editable Exam',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->assertFormSet([
            'type' => ResourceType::PracticeExams->value,
        ])
        ->fillForm([
            'exam_instructions' => 'Answer every question.',
            'quiz_questions' => [
                [
                    'question' => StudyRichText::forEditor('Edited MCQ about anthropology?'),
                    'options' => [
                        ['body' => StudyRichText::forEditor('One')],
                        ['body' => StudyRichText::forEditor('Two')],
                        ['body' => StudyRichText::forEditor('Three')],
                        ['body' => StudyRichText::forEditor('Four')],
                    ],
                    'answerIndex' => 2,
                    'explanation' => StudyRichText::forEditor('Because.'),
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $resource->refresh();

    expect($resource->content['payload']['questions'])->toHaveCount(1)
        ->and(StudyRichText::toPlain($resource->content['payload']['questions'][0]['question']))->toContain('Edited MCQ about anthropology?')
        ->and(StudyRichText::toPlain($resource->playerPayload()['questions'][0]['question']))->toContain('Edited MCQ about anthropology?')
        ->and($resource->generation_kind)->toBe(CollegeResourceKind::Exam);
});

it('rejects invalid content json on the learning edit page for non-interactive types', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'generation_kind' => CollegeResourceKind::Notes,
        'content' => [
            'kind' => CollegeResourceKind::Notes->value,
            'payload' => [
                'summary' => 'Original notes',
            ],
        ],
    ]);

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->fillForm([
            'content' => '{not-valid-json',
        ])
        ->call('save')
        ->assertHasFormErrors(['content']);
});

it('shows stored disk file paths with public and local exists status on edit', function () {
    Storage::fake('public');
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/gat-samples-rw.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 on-local-only');

    $resource = LearningResource::factory()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
    ]);

    $this->actingAs($admin);

    $expected = LearningResourceFiles::formatDiskPathStatusLines([$path]);

    expect($expected)->toContain($path)
        ->and($expected)->toContain('public: no')
        ->and($expected)->toContain('local: yes');

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->assertSee($path, false)
        ->assertSee('public: no', false)
        ->assertSee('local: yes', false);
});
