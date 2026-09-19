<?php

use App\Enums\CollegeResourceKind;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves edited content json from the learning edit page', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Editable Exam',
    ]);

    $updatedContent = [
        'kind' => CollegeResourceKind::Exam->value,
        'scope_type' => 'section',
        'scope_id' => 'section-1',
        'payload' => [
            'instructions' => 'Answer every question.',
            'questions' => [
                [
                    'question' => 'Edited MCQ about anthropology?',
                    'options' => ['One', 'Two', 'Three', 'Four'],
                    'answerIndex' => 2,
                    'explanation' => 'Because.',
                    'difficulty' => 'easy',
                ],
            ],
        ],
    ];

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->fillForm([
            'content' => json_encode($updatedContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $resource->refresh();

    expect($resource->content['payload']['questions'])->toHaveCount(1)
        ->and($resource->content['payload']['questions'][0]['question'])->toBe('Edited MCQ about anthropology?')
        ->and($resource->playerPayload()['questions'][0]['question'])->toBe('Edited MCQ about anthropology?');
});

it('rejects invalid content json on the learning edit page', function () {
    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $resource = LearningResource::factory()->exam()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->fillForm([
            'content' => '{not-valid-json',
        ])
        ->call('save')
        ->assertHasFormErrors(['content']);
});
