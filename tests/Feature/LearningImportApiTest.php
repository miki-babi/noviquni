<?php

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Support\StudyRichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.import.api_token' => 'test-import-token',
        'app.url' => 'https://noviquni.test',
    ]);
});

function importHeaders(): array
{
    return ['Authorization' => 'Bearer test-import-token'];
}

function sampleQuizPayload(Course $course, array $overrides = []): array
{
    return array_replace_recursive([
        'type' => ResourceType::Quiz->value,
        'course' => $course->slug,
        'title' => 'Hawassa Handbook - Algebra Quiz 1',
        'description' => '9-question multiple-choice quiz',
        'time_limit_minutes' => 14,
        'sort_order' => 0,
        'is_published' => true,
        'is_premium' => true,
        'content' => [
            'kind' => 'exam',
            'payload' => [
                'questions' => [
                    [
                        'question' => 'What is 2+2?',
                        'options' => ['3', '4', '5', '6'],
                        'answerIndex' => 1,
                        'explanation' => '2+2=4.',
                    ],
                ],
            ],
        ],
    ], $overrides);
}

it('rejects import api calls without a valid token', function () {
    $this->postJson('/api/learnings', [])
        ->assertUnauthorized();
});

it('creates a quiz learning resource from plain-text content', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $response = $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course))
        ->assertCreated()
        ->assertJsonStructure(['id', 'slug', 'url']);

    $resource = LearningResource::query()->findOrFail($response->json('id'));

    expect($resource->slug)->toBe('hawassa-handbook-algebra-quiz-1')
        ->and($resource->type)->toBe(ResourceType::Quiz)
        ->and($resource->generation_kind)->toBe(CollegeResourceKind::Quiz)
        ->and($resource->content['kind'])->toBe('quiz')
        ->and($resource->content['payload']['timeLimitMinutes'])->toBe(14)
        ->and($resource->is_published)->toBeTrue()
        ->and($resource->is_premium)->toBeTrue()
        ->and(StudyRichText::toPlain($resource->content['payload']['questions'][0]['question']))->toBe('What is 2+2?')
        ->and(StudyRichText::toPlain($resource->content['payload']['questions'][0]['options'][1]))->toBe('4')
        ->and($response->json('url'))->toContain('/admin/learnings/');
});

it('returns 422 when a question has more than six options', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'content' => [
                'payload' => [
                    'questions' => [
                        [
                            'question' => 'Pick one',
                            'options' => ['a', 'b', 'c', 'd', 'e', 'f', 'g'],
                            'answerIndex' => 0,
                        ],
                    ],
                ],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content.payload.questions.0.options']);
});

it('returns 422 when answerIndex is out of range', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'content' => [
                'payload' => [
                    'questions' => [
                        [
                            'question' => 'Pick one',
                            'options' => ['a', 'b'],
                            'answerIndex' => 5,
                        ],
                    ],
                ],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content.payload.questions.0.answerIndex']);
});

it('returns the existing resource for a repeated external_id', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $first = $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'external_id' => 'gat-algebra-1',
        ]))
        ->assertCreated()
        ->json();

    $second = $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'external_id' => 'gat-algebra-1',
            'title' => 'Different title should not create another row',
        ]))
        ->assertOk()
        ->json();

    expect($second['id'])->toBe($first['id'])
        ->and($second['slug'])->toBe($first['slug'])
        ->and(LearningResource::query()->count())->toBe(1);
});

it('rejects a duplicate title within the same course', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course))
        ->assertCreated();

    $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'description' => 'Retry without external_id',
        ]))
        ->assertStatus(409)
        ->assertJsonPath('slug', 'hawassa-handbook-algebra-quiz-1');
});

it('uploads an image and attaches it to a question', function () {
    Storage::fake('public');

    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $upload = $this->withHeaders(importHeaders())
        ->post('/api/uploads', [
            'file' => UploadedFile::fake()->image('diagram.png', 40, 40),
        ])
        ->assertCreated()
        ->json();

    expect($upload['path'])->toStartWith('learning-resources/quiz-content/general/')
        ->and(Storage::disk('public')->exists($upload['path']))->toBeTrue();

    $response = $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course, [
            'title' => 'Quiz with diagram',
            'content' => [
                'payload' => [
                    'questions' => [
                        [
                            'question' => 'Which diagram is correct?',
                            'images' => [$upload['path']],
                            'options' => ['A', 'B', 'C', 'D'],
                            'answerIndex' => 1,
                        ],
                    ],
                ],
            ],
        ]))
        ->assertCreated();

    $resource = LearningResource::query()->findOrFail($response->json('id'));
    $questionHtml = StudyRichText::toHtml($resource->content['payload']['questions'][0]['question']);

    expect($questionHtml)->toContain(basename($upload['path']));
});

it('lists learnings and patches metadata', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $created = $this->withHeaders(importHeaders())
        ->postJson('/api/learnings', sampleQuizPayload($course))
        ->assertCreated()
        ->json();

    $this->withHeaders(importHeaders())
        ->getJson('/api/learnings?per_page=100')
        ->assertOk()
        ->assertJsonPath('data.0.id', $created['id'])
        ->assertJsonPath('data.0.course', 'maths')
        ->assertJsonPath('data.0.question_count', 1)
        ->assertJsonPath('data.0.is_published', true);

    $this->withHeaders(importHeaders())
        ->patchJson('/api/learnings/'.$created['slug'], [
            'description' => 'Updated description',
            'is_published' => false,
            'time_limit_minutes' => 20,
        ])
        ->assertOk();

    $resource = LearningResource::query()->where('slug', $created['slug'])->firstOrFail();

    expect($resource->description)->toBe('Updated description')
        ->and($resource->is_published)->toBeFalse()
        ->and($resource->content['payload']['timeLimitMinutes'])->toBe(20);
});
