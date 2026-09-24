<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\TelegramFileAsset;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.file_admin_username' => 'vault_admin',
        'queue.default' => 'sync',
    ]);
});

it('stores a document from a file vault admin and replies with file_id', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9101,
        'message' => [
            'message_id' => 1,
            'chat' => ['id' => 777001, 'type' => 'private'],
            'from' => [
                'id' => 777001,
                'first_name' => 'Admin',
                'username' => 'Vault_Admin',
            ],
            'document' => [
                'file_id' => 'BQACAgQAAxkBAAI-test-file-id',
                'file_unique_id' => 'AgADunique-test-1',
                'file_name' => 'week-1-notes.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 12345,
            ],
        ],
    ])->assertOk();

    $asset = TelegramFileAsset::query()->where('file_unique_id', 'AgADunique-test-1')->first();

    expect($asset)->not->toBeNull()
        ->and($asset->file_id)->toBe('BQACAgQAAxkBAAI-test-file-id')
        ->and($asset->file_name)->toBe('week-1-notes.pdf')
        ->and($asset->uploaded_by_username)->toBe('Vault_Admin');

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return str_contains($text, 'File stored')
            && str_contains($text, 'BQACAgQAAxkBAAI-test-file-id')
            && str_contains($text, 'week-1-notes.pdf');
    });
});

it('does not store documents from non-admin users', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9102,
        'message' => [
            'message_id' => 2,
            'chat' => ['id' => 777002, 'type' => 'private'],
            'from' => [
                'id' => 777002,
                'first_name' => 'Student',
                'username' => 'random_student',
            ],
            'document' => [
                'file_id' => 'BQACAgQAAxkBAAI-should-not-store',
                'file_unique_id' => 'AgADunique-test-2',
                'file_name' => 'sneaky.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 100,
            ],
        ],
    ])->assertOk();

    expect(TelegramFileAsset::query()->count())->toBe(0);
});

it('sends telegram_files by file_id without uploading from disk', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 100]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555880',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $fileId = 'BQACAgQAAxkBAAI-resource-delivery';

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Telegram Vault Notes',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'generation_kind' => null,
        'content' => null,
        'is_premium' => false,
        'files' => null,
        'telegram_files' => [
            ['file_id' => $fileId, 'file_name' => 'vault-notes.pdf'],
        ],
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9103,
        'callback_query' => [
            'id' => 'cb-9103',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555880,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 50,
                'chat' => ['id' => 555880],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(function ($request) use ($fileId) {
        if (! str_contains($request->url(), '/sendDocument')) {
            return false;
        }

        $data = $request->data();

        return ($data['document'] ?? null) === $fileId
            && ($data['caption'] ?? null) === 'Telegram Vault Notes';
    });
});

it('still sends disk files when telegram_files is empty', function () {
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $relativePath = 'learnings/fallback-notes.pdf';
    Storage::disk($disk)->put($relativePath, 'fake-pdf-bytes');

    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 101]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => '555881',
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Disk Fallback Notes',
        'stream_id' => $stream->id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'generation_kind' => null,
        'content' => null,
        'is_premium' => false,
        'files' => [$relativePath],
        'telegram_files' => null,
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 9104,
        'callback_query' => [
            'id' => 'cb-9104',
            'data' => 'open_resource:'.$resource->id,
            'from' => [
                'id' => 555881,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
            'message' => [
                'message_id' => 51,
                'chat' => ['id' => 555881],
                'text' => 'Resources',
            ],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});
