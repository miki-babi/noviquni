<?php

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\College\LearningResourceMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('maps a quiz API response into learning resource attributes', function () {
    $mapped = app(LearningResourceMapper::class)->map(
        CollegeResourceKind::Quiz,
        [
            'success' => true,
            'fromCache' => false,
            'generatedAt' => '2026-09-15T12:46:32.167Z',
            'data' => [
                'questions' => [
                    [
                        'question' => 'What is anthropology?',
                        'options' => ['A', 'B', 'C', 'D'],
                        'answerIndex' => 1,
                        'explanation' => 'Because.',
                        'difficulty' => 'easy',
                    ],
                ],
            ],
        ],
        ['sectionId' => 'section-1'],
        ['scope_label' => 'Section 1: Definition'],
    );

    expect($mapped['type'])->toBe(ResourceType::PracticeQuestion)
        ->and($mapped['generation_kind'])->toBe(CollegeResourceKind::Quiz)
        ->and($mapped['title'])->toContain('Quiz')
        ->and($mapped['content']['kind'])->toBe('quiz')
        ->and($mapped['content']['scope_id'])->toBe('section-1')
        ->and($mapped['content']['payload']['questions'])->toHaveCount(1);
});

it('creates a learning resource from college API generated content via the create page', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/courses' => Http::response([
            'success' => true,
            'data' => [
                [
                    '_id' => 'course-1',
                    'courseCode' => 'ANTH101',
                    'name' => 'Anthropology',
                    'stream' => 'social-science',
                    'totalModules' => 1,
                ],
            ],
        ]),
        'api.noviq.et/api/public/college/courses/course-1/modules' => Http::response([
            'success' => true,
            'data' => [
                [
                    '_id' => 'module-1',
                    'name' => 'Anthropology',
                    'moduleNumber' => 1,
                    'totalUnits' => 1,
                    'totalSections' => 8,
                ],
            ],
        ]),
        'api.noviq.et/api/public/college/modules/module-1/curriculum' => Http::response([
            'success' => true,
            'data' => [
                'module' => [
                    '_id' => 'module-1',
                    'name' => 'Anthropology',
                ],
                'units' => [
                    [
                        '_id' => 'unit-1',
                        'unitNumber' => 1,
                        'name' => 'Introduction',
                        'sections' => [
                            [
                                '_id' => 'section-1',
                                'sectionNumber' => 1,
                                'name' => 'Definition',
                                'vectorEmbedded' => true,
                            ],
                        ],
                    ],
                ],
                'standaloneSections' => [],
            ],
        ]),
    ]);

    $admin = User::factory()->admin()->create();
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $mapped = app(LearningResourceMapper::class)->map(
        CollegeResourceKind::Quiz,
        [
            'success' => true,
            'fromCache' => false,
            'generatedAt' => '2026-09-15T12:46:32.167Z',
            'data' => [
                'questions' => [
                    [
                        'question' => 'What is anthropology?',
                        'options' => [
                            'Option A',
                            'Option B',
                            'Option C',
                            'Option D',
                        ],
                        'answerIndex' => 1,
                        'explanation' => 'Because.',
                        'difficulty' => 'medium',
                    ],
                ],
            ],
        ],
        ['sectionId' => 'section-1'],
        ['scope_label' => 'Definition of Anthropology'],
    );

    $this->actingAs($admin);

    Livewire::test(CreateLearning::class)
        ->fillForm([
            'creation_mode' => 'generate',
            'stream_id' => $stream->id,
            'course_id' => $course->id,
            'is_premium' => false,
            'is_published' => false,
            'api_course_id' => 'course-1',
            'api_module_id' => 'module-1',
            'api_scope_type' => 'sectionId',
            'api_unit_id' => 'unit-1',
            'api_section_id' => 'section-1',
            'title' => $mapped['title'],
            'slug' => $mapped['slug'],
            'description' => $mapped['description'],
            'topics' => $mapped['topics'],
            'type' => $mapped['type']->value,
            'generation_kind' => $mapped['generation_kind']->value,
            'content' => json_encode($mapped['content']),
            'is_indexable' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $resource = LearningResource::query()->first();

    expect($resource)->not->toBeNull()
        ->and($resource->type)->toBe(ResourceType::PracticeQuestion)
        ->and($resource->generation_kind)->toBe(CollegeResourceKind::Quiz)
        ->and($resource->content['kind'])->toBe('quiz')
        ->and($resource->content['payload']['questions'][0]['question'])->toBe('What is anthropology?')
        ->and($resource->course_id)->toBe($course->id)
        ->and($resource->is_published)->toBeFalse();
});

it('allows admins to open the create resource page', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/courses' => Http::response([
            'success' => true,
            'data' => [
                [
                    '_id' => 'course-1',
                    'courseCode' => 'ANTH101',
                    'name' => 'Anthropology',
                    'stream' => 'social-science',
                    'totalModules' => 1,
                ],
            ],
        ]),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/learnings/create')
        ->assertOk();
});
