<?php

use App\Enums\ResourceType;
use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Filament\Resources\Learnings\Pages\EditLearning;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Study\ExamQuestionZipImporter;
use App\Support\StudyRichText;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ZipArchive;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $manifest
 * @param  array<string, string>  $images  filename => binary contents
 */
function makeExamImportZip(array $manifest, array $images = []): string
{
    $dir = storage_path('app/tmp/exam-import-test-'.Str::uuid()->toString());
    $imagesDir = $dir.DIRECTORY_SEPARATOR.'images';
    File::ensureDirectoryExists($imagesDir);

    file_put_contents(
        $dir.DIRECTORY_SEPARATOR.'questions.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    );

    foreach ($images as $name => $contents) {
        file_put_contents($imagesDir.DIRECTORY_SEPARATOR.$name, $contents);
    }

    $zipPath = $dir.'.zip';
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFile($dir.DIRECTORY_SEPARATOR.'questions.json', 'questions.json');

    foreach (array_keys($images) as $name) {
        $zip->addFile($imagesDir.DIRECTORY_SEPARATOR.$name, 'images/'.$name);
    }

    $zip->close();
    File::deleteDirectory($dir);

    return $zipPath;
}

function tinyPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        true,
    );
}

function documentHasImageId(mixed $document, string $needle): bool
{
    if (! is_array($document)) {
        return false;
    }

    $json = json_encode($document, JSON_UNESCAPED_SLASHES) ?: '';

    return str_contains($json, '"type":"image"') && str_contains($json, $needle);
}

it('imports questions with tip tap image nodes from a zip', function () {
    $course = Course::factory()->create(['stream_id' => null]);
    $png = tinyPng();
    $zipPath = makeExamImportZip([
        'title' => 'Imported exam',
        'instructions' => 'Read carefully.',
        'timeLimitMinutes' => 45,
        'questions' => [
            [
                'question' => [
                    'text' => 'Which diagram is correct?',
                    'images' => ['q1.png'],
                ],
                'options' => [
                    ['text' => 'Option A'],
                    ['text' => 'Option B', 'images' => ['choice-b.png']],
                    ['text' => 'Option C'],
                    ['text' => 'Option D'],
                ],
                'answerIndex' => 1,
                'explanation' => [
                    'text' => 'B matches the figure.',
                    'images' => ['explain-1.png'],
                ],
            ],
            [
                'question' => 'Plain shorthand question?',
                'options' => ['Yes', 'No'],
                'answerIndex' => 0,
                'explanation' => 'Shorthand works.',
            ],
        ],
    ], [
        'q1.png' => $png,
        'choice-b.png' => $png,
        'explain-1.png' => $png,
    ]);

    $result = app(ExamQuestionZipImporter::class)->import($zipPath, $course->id);
    @unlink($zipPath);

    expect($result['errors'])->toBeEmpty()
        ->and($result['title'])->toBe('Imported exam')
        ->and($result['instructions'])->toBe('Read carefully.')
        ->and($result['timeLimitMinutes'])->toBe(45)
        ->and($result['questions'])->toHaveCount(2)
        ->and($result['questions'][0]['answerIndex'])->toBe(1)
        ->and(StudyRichText::toPlain($result['questions'][0]['question']))->toContain('Which diagram is correct?')
        ->and(StudyRichText::toPlain($result['questions'][0]['options'][1]['body']))->toContain('Option B')
        ->and(StudyRichText::toPlain($result['questions'][0]['explanation']))->toContain('B matches the figure.')
        ->and(documentHasImageId($result['questions'][0]['question'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and(documentHasImageId($result['questions'][0]['options'][1]['body'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and(documentHasImageId($result['questions'][0]['explanation'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and(StudyRichText::toHtml($result['questions'][0]['question']))->toContain('quiz-content/'.$course->id.'/')
        ->and(StudyRichText::toPlain($result['questions'][1]['question']))->toBe('Plain shorthand question?');
});

it('reports missing questions.json', function () {
    $dir = storage_path('app/tmp/exam-import-empty-'.Str::uuid()->toString());
    File::ensureDirectoryExists($dir);
    $zipPath = $dir.'.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'no json here');
    $zip->close();
    File::deleteDirectory($dir);

    $result = app(ExamQuestionZipImporter::class)->import($zipPath, 1);
    @unlink($zipPath);

    expect($result['questions'])->toBeEmpty()
        ->and($result['errors'][0] ?? null)->toContain('questions.json');
});

it('reports a missing image file', function () {
    $zipPath = makeExamImportZip([
        'questions' => [
            [
                'question' => [
                    'text' => 'Missing image?',
                    'images' => ['missing.png'],
                ],
                'options' => ['A', 'B'],
                'answerIndex' => 0,
            ],
        ],
    ]);

    $result = app(ExamQuestionZipImporter::class)->import($zipPath, 1);
    @unlink($zipPath);

    expect($result['questions'])->toBeEmpty()
        ->and(implode(' ', $result['errors']))->toContain('image not found');
});

it('reports fewer than two options and a bad answer index', function () {
    $zipPath = makeExamImportZip([
        'questions' => [
            [
                'question' => 'Only one option?',
                'options' => ['Alone'],
                'answerIndex' => 0,
            ],
            [
                'question' => 'Bad answer index?',
                'options' => ['A', 'B'],
                'answerIndex' => 5,
            ],
        ],
    ]);

    $result = app(ExamQuestionZipImporter::class)->import($zipPath, 1);
    @unlink($zipPath);

    expect($result['questions'])->toBeEmpty()
        ->and(implode(' ', $result['errors']))->toContain('at least 2 options')
        ->and(implode(' ', $result['errors']))->toContain('answerIndex');
});

it('builds a downloadable sample zip that the importer accepts', function () {
    $course = Course::factory()->create(['stream_id' => null]);
    $bytes = app(ExamQuestionZipImporter::class)->sampleZipContents();
    $zipPath = storage_path('app/tmp/sample-'.Str::uuid()->toString().'.zip');
    file_put_contents($zipPath, $bytes);

    $result = app(ExamQuestionZipImporter::class)->import($zipPath, $course->id);
    @unlink($zipPath);

    expect($result['errors'])->toBeEmpty()
        ->and($result['questions'])->toHaveCount(2)
        ->and($result['title'])->toBe('Sample exam');
});

it('fills quiz_questions on create learning via the import zip action', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['stream_id' => null]);
    $png = tinyPng();
    $zipPath = makeExamImportZip([
        'title' => 'Livewire exam',
        'instructions' => 'Go.',
        'timeLimitMinutes' => 20,
        'questions' => [
            [
                'question' => [
                    'text' => 'Imported with image?',
                    'images' => ['q1.png'],
                ],
                'options' => [
                    ['text' => 'Yes', 'images' => ['choice-b.png']],
                    ['text' => 'No'],
                ],
                'answerIndex' => 0,
                'explanation' => [
                    'text' => 'Because images copied.',
                    'images' => ['explain-1.png'],
                ],
            ],
        ],
    ], [
        'q1.png' => $png,
        'choice-b.png' => $png,
        'explain-1.png' => $png,
    ]);

    $upload = UploadedFile::fake()->createWithContent('exam-import.zip', (string) file_get_contents($zipPath));
    @unlink($zipPath);

    $this->actingAs($admin);

    $component = Livewire::test(CreateLearning::class)
        ->fillForm([
            'type' => ResourceType::PracticeExams->value,
            'title' => 'Practice from zip',
            'slug' => 'practice-from-zip',
            'course_id' => $course->id,
            'sort_order' => 0,
            'is_premium' => false,
            'is_published' => false,
            'is_indexable' => true,
            'file_source' => 'telegram',
            'telegram_file_ids' => [],
        ])
        ->callAction(
            TestAction::make('importQuestionsZip')->schemaComponent('quizQuestionsSection'),
            [
                'archive' => $upload,
                'mode' => 'replace',
            ],
        )
        ->assertNotified();

    $questions = $component->get('data.quiz_questions');

    expect($questions)->toBeArray()
        ->and($questions)->toHaveCount(1)
        ->and($questions[0]['answerIndex'])->toBe(0)
        ->and(documentHasImageId($questions[0]['question'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and(documentHasImageId($questions[0]['options'][0]['body'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and(documentHasImageId($questions[0]['explanation'], 'quiz-content/'.$course->id.'/'))->toBeTrue()
        ->and($component->get('data.exam_title'))->toBe('Livewire exam')
        ->and($component->get('data.exam_instructions'))->toBe('Go.')
        ->and((int) $component->get('data.exam_time_limit_minutes'))->toBe(20);
});

it('appends imported questions on edit learning', function () {
    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['stream_id' => null]);
    $resource = LearningResource::factory()->quiz()->create([
        'course_id' => $course->id,
        'content' => [
            'kind' => 'quiz',
            'payload' => [
                'questions' => [
                    [
                        'question' => 'Existing question?',
                        'options' => ['A', 'B'],
                        'answerIndex' => 0,
                        'explanation' => null,
                    ],
                ],
            ],
        ],
    ]);

    $zipPath = makeExamImportZip([
        'questions' => [
            [
                'question' => 'Appended question?',
                'options' => ['Yes', 'No'],
                'answerIndex' => 1,
            ],
        ],
    ]);
    $upload = UploadedFile::fake()->createWithContent('append.zip', (string) file_get_contents($zipPath));
    @unlink($zipPath);

    $this->actingAs($admin);

    $component = Livewire::test(EditLearning::class, ['record' => $resource->getRouteKey()])
        ->callAction(
            TestAction::make('importQuestionsZip')->schemaComponent('quizQuestionsSection'),
            [
                'archive' => $upload,
                'mode' => 'append',
            ],
        )
        ->assertNotified();

    $questions = $component->get('data.quiz_questions');

    expect($questions)->toHaveCount(2)
        ->and(StudyRichText::toPlain($questions[0]['question']))->toContain('Existing question')
        ->and(StudyRichText::toPlain($questions[1]['question']))->toContain('Appended question')
        ->and($questions[1]['answerIndex'])->toBe(1);
});
