<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\LearningResourceFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
function pdfViewerContext(): array
{
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);
    $user->courses()->sync([$course->id]);

    return [$user, $course, $stream];
}

it('streams a disk PDF for an enrolled student', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/week-1.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 fake-pdf-bytes');

    $resource = LearningResource::factory()->published()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Week 1 PDF notes',
    ]);

    $response = $this->actingAs($user)
        ->get(route('tg.files.show', ['resource' => $resource, 'index' => 0]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->streamedContent())->toContain('%PDF-1.4 fake-pdf-bytes');
});

it('proxies a telegram vault PDF through the stream route', function () {
    [$user, $course, $stream] = pdfViewerContext();

    Http::fake([
        'api.telegram.org/bot*/getFile' => Http::response([
            'ok' => true,
            'result' => [
                'file_id' => 'file-pdf-1',
                'file_path' => 'documents/week-1.pdf',
            ],
        ], 200),
        'api.telegram.org/file/bot*/documents/week-1.pdf' => Http::response('%PDF-1.4 vault-bytes', 200),
    ]);

    $resource = LearningResource::factory()->published()->withTelegramFiles([
        ['file_id' => 'file-pdf-1', 'file_name' => 'week-1.pdf'],
    ])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Vault PDF notes',
    ]);

    $this->actingAs($user)
        ->get(route('tg.files.show', ['resource' => $resource, 'index' => 0]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('%PDF-1.4 vault-bytes', false);
});

it('shows the bootstrap page for guests on the PDF stream', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/week-1.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 fake-pdf-bytes');

    $resource = LearningResource::factory()->published()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
    ]);

    $this->get(route('tg.files.show', ['resource' => $resource, 'index' => 0]))
        ->assertOk()
        ->assertSee('tg-session-form', false);
});

it('shows the locked view when streaming a premium PDF without entitlement', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/premium.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 premium');

    $resource = LearningResource::factory()->published()->premium()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Premium PDF',
    ]);

    $this->actingAs($user)
        ->get(route('tg.files.show', ['resource' => $resource, 'index' => 0]))
        ->assertOk()
        ->assertSee('Premium resource', false);
});

it('returns 404 for a non-PDF attachment index', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $docx = LearningResourceFiles::directoryForCourse($course->id).'/notes.docx';
    Storage::disk($disk)->put($docx, 'not-a-pdf');

    $resource = LearningResource::factory()->published()->withFiles([$docx])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
    ]);

    expect($resource->pdfAttachments())->toBe([]);

    $this->actingAs($user)
        ->get(route('tg.files.show', ['resource' => $resource, 'index' => 0]))
        ->assertNotFound();
});

it('links PDF resources from the hub to the Mini App player', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/hub.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 hub');

    $resource = LearningResource::factory()->published()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Hub PDF notes',
    ]);

    expect($resource->opensInMiniApp())->toBeTrue()
        ->and($resource->miniAppUrl())->toBe(route('tg.play.reference-books', $resource));

    $this->actingAs($user)
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'notes']))
        ->assertOk()
        ->assertSee('Hub PDF notes')
        ->assertSee($resource->miniAppUrl(), false)
        ->assertDontSee('?start=resource_'.$resource->id, false)
        ->assertDontSee('data-tg-bot-link', false);
});

it('keeps non-PDF file resources on the bot deep link', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $docx = LearningResourceFiles::directoryForCourse($course->id).'/notes.docx';
    Storage::disk($disk)->put($docx, 'word-bytes');

    $resource = LearningResource::factory()->published()->withFiles([$docx])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Word notes',
    ]);

    expect($resource->opensInMiniApp())->toBeFalse();

    $this->actingAs($user)
        ->get(route('tg.courses.hub', ['course' => $course, 'hub' => 'notes']))
        ->assertOk()
        ->assertSee('Word notes')
        ->assertSee('?start=resource_'.$resource->id, false)
        ->assertSee('data-tg-bot-link', false)
        ->assertDontSee($resource->miniAppUrl(), false);
});

it('renders the PDF viewer markup on the notes player with a relative stream URL', function () {
    [$user, $course, $stream] = pdfViewerContext();

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/player.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 player');

    $resource = LearningResource::factory()->published()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Player PDF notes',
    ]);

    $streamUrl = route('tg.files.show', [
        'resource' => $resource,
        'index' => 0,
    ], absolute: false);

    $this->actingAs($user)
        ->get(route('tg.play.reference-books', $resource))
        ->assertOk()
        ->assertSee('data-pdf-viewer', false)
        ->assertSee('data-pdf-url="'.$streamUrl.'"', false)
        ->assertDontSee('data-pdf-url="https://', false)
        ->assertSee('player.pdf', false)
        ->assertSee('pdf.js', false);
});

it('opens the PDF notes player for an unenrolled student and enrolls them', function () {
    $stream = Stream::factory()->create();
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
    ]);

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
        'is_active' => true,
    ]);

    $disk = LearningResourceFiles::diskName();
    Storage::fake($disk);

    $path = LearningResourceFiles::directoryForCourse($course->id).'/unenrolled.pdf';
    Storage::disk($disk)->put($path, '%PDF-1.4 unenrolled');

    $resource = LearningResource::factory()->published()->withFiles([$path])->create([
        'course_id' => $course->id,
        'stream_id' => $stream->id,
        'type' => ResourceType::ReferenceBooks,
        'title' => 'Unenrolled PDF notes',
    ]);

    $streamUrl = route('tg.files.show', [
        'resource' => $resource,
        'index' => 0,
    ], absolute: false);

    expect($user->courses()->where('courses.id', $course->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('tg.play.reference-books', $resource))
        ->assertOk()
        ->assertSee('data-pdf-viewer', false)
        ->assertSee('data-pdf-url="'.$streamUrl.'"', false)
        ->assertSee('Unenrolled PDF notes', false);

    expect($user->fresh()->courses()->where('courses.id', $course->id)->exists())->toBeTrue();
});
