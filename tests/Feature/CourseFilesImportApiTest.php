<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Filament\Pages\ManageCourseFiles;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\TelegramFileAsset;
use App\Models\User;
use App\Support\LearningResourceFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.import.api_token' => 'test-import-token',
        'services.telegram.bot_token' => 'test-token',
        'app.url' => 'https://noviquni.test',
        'filesystems.default' => 'local',
    ]);
});

function courseFilesImportHeaders(): array
{
    return ['Authorization' => 'Bearer test-import-token'];
}

it('rejects course file api calls without a valid token', function () {
    $this->post('/api/course-files', [])
        ->assertUnauthorized();
});

it('uploads a course file into the learning-resources library path', function () {
    Storage::fake('local');

    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $response = $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('handbook.pdf', 120, 'application/pdf'),
            'title' => 'Hawassa University GAT Hand Book',
            'course' => 'maths',
            'description' => 'GAT handbook PDF',
            'external_id' => 'tg-unique-1',
            'telegram_file_id' => 'BQAC-file-1',
            'is_published' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('title', 'Hawassa University GAT Hand Book')
        ->assertJsonPath('course', 'maths');

    $path = $response->json('path');

    expect($path)->toStartWith('learning-resources/'.$course->id.'/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();

    $courseFile = CourseFile::query()->findOrFail($response->json('id'));

    expect($courseFile->external_id)->toBe('tg-unique-1')
        ->and($courseFile->telegram_file_id)->toBe('BQAC-file-1')
        ->and($courseFile->is_published)->toBeTrue();
});

it('returns the existing course file when external_id already exists', function () {
    Storage::fake('local');

    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);
    $existing = CourseFile::factory()->create([
        'course_id' => $course->id,
        'title' => 'Existing',
        'external_id' => 'tg-dup-1',
        'path' => LearningResourceFiles::directoryForCourse($course->id).'/existing.pdf',
        'disk' => 'local',
        'size' => 10,
    ]);

    $response = $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('other.pdf', 50, 'application/pdf'),
            'title' => 'Should not create',
            'course' => 'maths',
            'external_id' => 'tg-dup-1',
        ])
        ->assertOk()
        ->assertJsonPath('id', $existing->id)
        ->assertJsonPath('title', 'Existing');

    expect(CourseFile::query()->count())->toBe(1);
});

it('rejects invalid course slugs and unsupported file types', function () {
    Storage::fake('local');

    Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('handbook.pdf', 20, 'application/pdf'),
            'title' => 'Missing course',
            'course' => 'not-a-course',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course']);

    $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('notes.exe', 20, 'application/octet-stream'),
            'title' => 'Bad type',
            'course' => 'maths',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('accepts zip and plain text course file uploads', function () {
    Storage::fake('local');

    Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('pack.zip', 40, 'application/zip'),
            'title' => 'Zip pack',
            'course' => 'maths',
        ])
        ->assertCreated();

    $this->withHeaders(courseFilesImportHeaders())
        ->post('/api/course-files', [
            'file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
            'title' => 'Notes',
            'course' => 'maths',
        ])
        ->assertCreated();
});

it('lists course files with pagination meta', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);

    CourseFile::factory()->count(2)->create(['course_id' => $course->id]);

    $this->withHeaders(courseFilesImportHeaders())
        ->getJson('/api/course-files?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(1, 'data');
});

it('patches course file metadata without moving the blob', function () {
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);
    $other = Course::factory()->create(['slug' => 'reading-and-writing', 'stream_id' => null]);
    $file = CourseFile::factory()->create([
        'course_id' => $course->id,
        'title' => 'Old title',
        'path' => LearningResourceFiles::directoryForCourse($course->id).'/keep.pdf',
        'is_published' => false,
    ]);

    $this->withHeaders(courseFilesImportHeaders())
        ->patchJson('/api/course-files/'.$file->id, [
            'title' => 'New title',
            'course' => 'reading-and-writing',
            'is_published' => true,
        ])
        ->assertOk()
        ->assertJsonPath('title', 'New title')
        ->assertJsonPath('course', 'reading-and-writing')
        ->assertJsonPath('is_published', true);

    expect($file->fresh()->path)->toBe($file->path)
        ->and($file->fresh()->course_id)->toBe($other->id);
});

it('lists telegram files and filters unmigrated ones', function () {
    $migrated = TelegramFileAsset::factory()->create([
        'file_name' => 'done.pdf',
        'stored_path' => 'learning-resources/1/done.pdf',
    ]);
    $pending = TelegramFileAsset::factory()->create([
        'file_name' => 'pending.pdf',
        'stored_path' => null,
        'course_file_id' => null,
    ]);

    $this->withHeaders(courseFilesImportHeaders())
        ->getJson('/api/telegram-files')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $response = $this->withHeaders(courseFilesImportHeaders())
        ->getJson('/api/telegram-files?unmigrated=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $pending->id)
        ->assertJsonPath('data.0.telegram_file_id', $pending->file_id)
        ->assertJsonPath('data.0.file_name', 'pending.pdf');

    expect($response->json('data.0.id'))->not->toBe($migrated->id);
});

it('patches telegram file migration links without changing file_id', function () {
    Storage::fake('local');

    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/migrated.pdf';
    Storage::disk('local')->put($path, 'pdf-bytes');

    $courseFile = CourseFile::factory()->create([
        'course_id' => $course->id,
        'path' => $path,
        'disk' => 'local',
    ]);

    $asset = TelegramFileAsset::factory()->create([
        'file_id' => 'KEEP-FILE-ID',
        'stored_path' => null,
        'course_file_id' => null,
    ]);

    $this->withHeaders(courseFilesImportHeaders())
        ->patchJson('/api/telegram-files/'.$asset->id, [
            'course_file_id' => $courseFile->id,
            'stored_path' => $path,
        ])
        ->assertOk()
        ->assertJsonPath('course_file_id', $courseFile->id)
        ->assertJsonPath('stored_path', $path)
        ->assertJsonPath('telegram_file_id', 'KEEP-FILE-ID');

    expect($asset->fresh()->file_id)->toBe('KEEP-FILE-ID');
});

it('prefers stored_path disk for pdf attachments from telegram files', function () {
    Storage::fake('local');

    $course = Course::factory()->create();
    $path = LearningResourceFiles::directoryForCourse($course->id).'/vault.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4');

    $asset = TelegramFileAsset::factory()->create([
        'file_id' => 'PDF-FILE-ID',
        'file_name' => 'vault.pdf',
        'stored_path' => $path,
    ]);

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'files' => null,
        'telegram_files' => [
            ['file_id' => $asset->file_id, 'file_name' => 'vault.pdf'],
        ],
    ]);

    $attachments = $resource->pdfAttachments();

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]['source'])->toBe('disk')
        ->and($attachments[0]['key'])->toBe($path);
});

it('sends migrated telegram resources from disk instead of telegram file_id', function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/notes.docx';
    Storage::disk('local')->put($path, 'doc-bytes');

    $asset = TelegramFileAsset::factory()->create([
        'file_id' => 'DOC-FILE-ID',
        'file_name' => 'notes.docx',
        'stored_path' => $path,
    ]);

    $user = User::factory()->student()->create([
        'telegram_id' => '700001',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_premium' => true,
        'is_active' => true,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'title' => 'Disk notes',
        'type' => ResourceType::ReferenceBooks,
        'files' => null,
        'telegram_files' => [
            ['file_id' => $asset->file_id, 'file_name' => 'notes.docx'],
        ],
        'is_premium' => false,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9101,
        'callback_query' => [
            'id' => 'cb-9101',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 700001,
                'first_name' => 'Test',
                'username' => 'tester',
            ],
            'message' => [
                'message_id' => 71,
                'chat' => ['id' => 700001],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendDocument')) {
            return false;
        }

        return ($request->data()['document'] ?? null) === 'DOC-FILE-ID';
    });
});

it('shows api-uploaded course files on the filament manage page', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $course = Course::factory()->create(['slug' => 'maths', 'stream_id' => null]);
    $path = LearningResourceFiles::directoryForCourse($course->id).'/api-file.pdf';
    Storage::disk('local')->put($path, 'pdf');

    CourseFile::factory()->create([
        'course_id' => $course->id,
        'title' => 'API uploaded handbook',
        'path' => $path,
        'disk' => 'local',
        'size' => 3,
    ]);

    $this->actingAs($admin);

    Livewire::test(ManageCourseFiles::class)
        ->fillForm(['course_id' => $course->id, 'files' => []])
        ->assertSee('API uploaded handbook');
});
