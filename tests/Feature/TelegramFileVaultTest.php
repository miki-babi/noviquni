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
        'services.telegram.bot_username' => 'noviquni_bot',
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
        ->and($asset->uploaded_by_username)->toBe('Vault_Admin')
        ->and(data_get(cache()->get('telegram.publish.buffer.777001'), 'prompt_message_id'))->toBe(10);

    Http::assertSent(function ($request) use ($asset) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'File stored')
            && str_contains($text, 'BQACAgQAAxkBAAI-test-file-id')
            && str_contains($text, 'week-1-notes.pdf')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'admin:publish:'.$asset->id
            && data_get($data, 'reply_markup.inline_keyboard.1.0.callback_data') === 'admin:publish:done';
    });
});

function adminDocumentWebhook(
    int $updateId,
    int $telegramId,
    string $fileId,
    string $uniqueId,
    string $fileName = 'week-1-notes.pdf',
    ?string $mediaGroupId = null,
): array {
    $payload = [
        'update_id' => $updateId,
        'message' => [
            'message_id' => $updateId,
            'chat' => ['id' => $telegramId, 'type' => 'private'],
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Admin',
                'username' => 'vault_admin',
            ],
            'document' => [
                'file_id' => $fileId,
                'file_unique_id' => $uniqueId,
                'file_name' => $fileName,
                'mime_type' => 'application/pdf',
                'file_size' => 12345,
            ],
        ],
    ];

    if ($mediaGroupId !== null) {
        $payload['message']['media_group_id'] = $mediaGroupId;
    }

    return $payload;
}

function adminCallbackWebhook(int $updateId, int $telegramId, string $data, int $messageId = 20): array
{
    return [
        'update_id' => $updateId,
        'callback_query' => [
            'id' => 'cb-'.$updateId,
            'data' => $data,
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Admin',
                'username' => 'vault_admin',
            ],
            'message' => [
                'message_id' => $messageId,
                'chat' => ['id' => $telegramId],
                'text' => 'previous',
            ],
        ],
    ];
}

it('publishes a learning resource through the telegram wizard', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 20]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create(['name' => 'Natural']);
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);

    $telegramId = 777010;

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9201,
        $telegramId,
        'BQACAgQAAxkBAAI-publish-file',
        'AgADpublish-unique-1',
        'calc-notes.pdf',
    ))->assertOk();

    $asset = TelegramFileAsset::query()->where('file_unique_id', 'AgADpublish-unique-1')->firstOrFail();

    $this->postJson('/telegram/webhook', adminCallbackWebhook(9202, $telegramId, 'admin:publish:'.$asset->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9203, $telegramId, 'admin:pub:stream:'.$stream->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9204, $telegramId, 'admin:pub:course:'.$course->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9205, $telegramId, 'admin:pub:type:notes'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9206, $telegramId, 'admin:pub:title:filename'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9207, $telegramId, 'admin:pub:premium:0'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9208, $telegramId, 'admin:pub:confirm'))->assertOk();

    $resource = LearningResource::query()->where('title', 'calc-notes')->first();

    expect($resource)->not->toBeNull()
        ->and($resource->is_published)->toBeTrue()
        ->and($resource->is_premium)->toBeFalse()
        ->and($resource->type)->toBe(ResourceType::Notes)
        ->and($resource->course_id)->toBe($course->id)
        ->and($resource->stream_id)->toBe($stream->id)
        ->and($resource->telegram_files)->toBe([
            ['file_id' => 'BQACAgQAAxkBAAI-publish-file', 'file_name' => 'calc-notes.pdf'],
        ]);
});

it('prompts for bulk publish after buffering an album of documents', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $telegramId = 777020;
    $mediaGroupId = 'album-777020';

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9301,
        $telegramId,
        'BQACAgQAAxkBAAI-album-1',
        'AgADalbum-unique-1',
        'chapter-1.pdf',
        $mediaGroupId,
    ))->assertOk();

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9302,
        $telegramId,
        'BQACAgQAAxkBAAI-album-2',
        'AgADalbum-unique-2',
        'chapter-2.pdf',
        $mediaGroupId,
    ))->assertOk();

    expect(TelegramFileAsset::query()->count())->toBe(2);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return str_contains($text, 'File stored')
            && str_contains($text, 'chapter-1.pdf');
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/editMessageText')) {
            return false;
        }

        $data = $request->data();
        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'Stored 2 files')
            && str_contains($text, 'chapter-1.pdf')
            && str_contains($text, 'chapter-2.pdf')
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'admin:publish:bulk'
            && data_get($data, 'reply_markup.inline_keyboard.1.0.callback_data') === 'admin:publish:separate'
            && data_get($data, 'reply_markup.inline_keyboard.2.0.callback_data') === 'admin:publish:done';
    });
});

it('bulk publishes multiple vault files through one shared wizard', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create(['name' => 'Natural']);
    $course = Course::factory()->create([
        'stream_id' => $stream->id,
        'name' => 'Physics',
        'is_active' => true,
    ]);

    $telegramId = 777021;
    $mediaGroupId = 'album-777021';

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9311,
        $telegramId,
        'BQACAgQAAxkBAAI-bulk-1',
        'AgADbulk-unique-1',
        'kinematics.pdf',
        $mediaGroupId,
    ))->assertOk();

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9312,
        $telegramId,
        'BQACAgQAAxkBAAI-bulk-2',
        'AgADbulk-unique-2',
        'dynamics.pdf',
        $mediaGroupId,
    ))->assertOk();

    $this->postJson('/telegram/webhook', adminCallbackWebhook(9313, $telegramId, 'admin:publish:bulk'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9314, $telegramId, 'admin:pub:stream:'.$stream->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9315, $telegramId, 'admin:pub:course:'.$course->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9316, $telegramId, 'admin:pub:type:module'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9317, $telegramId, 'admin:pub:premium:1'))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9318, $telegramId, 'admin:pub:confirm'))->assertOk();

    $resources = LearningResource::query()->orderBy('title')->get();

    expect($resources)->toHaveCount(2)
        ->and($resources->pluck('title')->all())->toBe(['dynamics', 'kinematics'])
        ->and($resources->every(fn (LearningResource $resource) => $resource->course_id === $course->id))->toBeTrue()
        ->and($resources->every(fn (LearningResource $resource) => $resource->type === ResourceType::Module))->toBeTrue()
        ->and($resources->every(fn (LearningResource $resource) => $resource->is_premium === true))->toBeTrue()
        ->and($resources->every(fn (LearningResource $resource) => $resource->is_published === true))->toBeTrue()
        ->and(cache()->get('telegram.publish.'.$telegramId))->toBeNull()
        ->and(cache()->get('telegram.publish.buffer.'.$telegramId))->toBeNull();
});

it('sends individual publish buttons when choosing publish separately', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $telegramId = 777022;
    $mediaGroupId = 'album-777022';

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9321,
        $telegramId,
        'BQACAgQAAxkBAAI-sep-1',
        'AgADsep-unique-1',
        'alpha.pdf',
        $mediaGroupId,
    ))->assertOk();

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9322,
        $telegramId,
        'BQACAgQAAxkBAAI-sep-2',
        'AgADsep-unique-2',
        'beta.pdf',
        $mediaGroupId,
    ))->assertOk();

    $assets = TelegramFileAsset::query()->orderBy('id')->get();

    $this->postJson('/telegram/webhook', adminCallbackWebhook(9323, $telegramId, 'admin:publish:separate'))->assertOk();

    expect(cache()->get('telegram.publish.buffer.'.$telegramId))->toBeNull();

    foreach ($assets as $asset) {
        Http::assertSent(function ($request) use ($asset) {
            if (! str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($text, 'File stored')
                && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'admin:publish:'.$asset->id;
        });
    }
});

it('cancels the publish wizard without creating a resource', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 10]], 200),
        'api.telegram.org/bot*/editMessageText' => Http::response(['ok' => true, 'result' => ['message_id' => 20]], 200),
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $stream = Stream::factory()->create(['name' => 'Natural']);
    Course::factory()->create(['stream_id' => $stream->id, 'is_active' => true]);

    $telegramId = 777011;

    $this->postJson('/telegram/webhook', adminDocumentWebhook(
        9211,
        $telegramId,
        'BQACAgQAAxkBAAI-cancel-file',
        'AgADcancel-unique-1',
    ))->assertOk();

    $asset = TelegramFileAsset::query()->where('file_unique_id', 'AgADcancel-unique-1')->firstOrFail();

    $this->postJson('/telegram/webhook', adminCallbackWebhook(9212, $telegramId, 'admin:publish:'.$asset->id))->assertOk();
    $this->postJson('/telegram/webhook', adminCallbackWebhook(9213, $telegramId, 'admin:pub:cancel'))->assertOk();

    expect(LearningResource::query()->count())->toBe(0)
        ->and(cache()->get('telegram.publish.'.$telegramId))->toBeNull();
});

it('rejects publish callbacks from non-admin users', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $asset = TelegramFileAsset::factory()->create();

    $this->postJson('/telegram/webhook', [
        'update_id' => 9221,
        'callback_query' => [
            'id' => 'cb-9221',
            'data' => 'admin:publish:'.$asset->id,
            'from' => [
                'id' => 777099,
                'first_name' => 'Student',
                'username' => 'random_student',
            ],
            'message' => [
                'message_id' => 20,
                'chat' => ['id' => 777099],
                'text' => 'previous',
            ],
        ],
    ])->assertOk();

    expect(LearningResource::query()->count())->toBe(0)
        ->and(cache()->get('telegram.publish.777099'))->toBeNull();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/answerCallbackQuery')) {
            return false;
        }

        $data = $request->data();

        return ($data['text'] ?? null) === 'Not allowed.'
            && ($data['show_alert'] ?? null) === true;
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

    Http::assertSent(function ($request) use ($fileId, $resource) {
        if (! str_contains($request->url(), '/sendDocument')) {
            return false;
        }

        $data = $request->data();
        $buttons = data_get($data, 'reply_markup.inline_keyboard.0', []);

        $shareUrl = (string) ($buttons[1]['url'] ?? '');

        return ($data['document'] ?? null) === $fileId
            && ($data['caption'] ?? null) === 'Telegram Vault Notes'
            && ($buttons[0]['callback_data'] ?? null) === 'save:resource:'.$resource->id
            && ($buttons[0]['text'] ?? null) === 'Quick save'
            && ! array_key_exists('style', $buttons[0])
            && str_contains($shareUrl, 't.me/share/url')
            && str_contains(urldecode($shareUrl), 'start=resource_'.$resource->id);
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
