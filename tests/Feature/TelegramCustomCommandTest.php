<?php

use App\Enums\OnboardingStep;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\ResourceDownload;
use App\Models\Stream;
use App\Models\TelegramCommand;
use App\Models\TelegramFileAsset;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'queue.default' => 'sync',
        'app.url' => 'https://noviquni.test',
    ]);
});

function customCommandUser(int $telegramId = 600100): User
{
    $stream = Stream::factory()->create();
    $course = Course::factory()->create(['stream_id' => $stream->id]);

    $user = User::factory()->student()->create([
        'telegram_id' => (string) $telegramId,
        'onboarding_step' => OnboardingStep::Complete,
        'stream_id' => $stream->id,
    ]);
    $user->courses()->attach($course->id);

    return $user;
}

function postCustomCommand(int $telegramId, string $text, int $updateId = 9201): void
{
    test()->postJson('/telegram/webhook', [
        'update_id' => $updateId,
        'message' => [
            'message_id' => 10,
            'text' => $text,
            'chat' => ['id' => $telegramId],
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Abebe',
                'username' => 'abebe',
            ],
        ],
    ])->assertOk();
}

it('sends the message and each vault file_id for an active command', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 2]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = customCommandUser(600101);
    $first = TelegramFileAsset::factory()->create(['file_id' => 'BQACAgQAAxkBAAI-cmd-file-1']);
    $second = TelegramFileAsset::factory()->create(['file_id' => 'BQACAgQAAxkBAAI-cmd-file-2']);

    $command = TelegramCommand::factory()->messageOnly('<b>Syllabus pack</b>')->create([
        'command' => 'syllabus',
    ]);
    $command->syncFileAssets([$second->id, $first->id]);

    postCustomCommand(600101, '/syllabus please', 9201);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return ($request->data()['text'] ?? null) === '<b>Syllabus pack</b>';
    });

    $documents = Http::recorded()
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/sendDocument'))
        ->map(fn ($pair) => $pair[0]->data()['document'] ?? null)
        ->values()
        ->all();

    expect($documents)->toBe([
        'BQACAgQAAxkBAAI-cmd-file-2',
        'BQACAgQAAxkBAAI-cmd-file-1',
    ]);
});

it('delivers a free learning resource and records a download', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendDocument' => Http::response(['ok' => true, 'result' => ['message_id' => 3]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = customCommandUser(600102);
    $course = $user->courses()->first();
    $fileId = 'BQACAgQAAxkBAAI-cmd-resource';

    $resource = LearningResource::factory()->published()->create([
        'title' => 'Command Notes',
        'stream_id' => $user->stream_id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'is_premium' => false,
        'files' => null,
        'telegram_files' => [
            ['file_id' => $fileId, 'file_name' => 'notes.pdf'],
        ],
    ]);

    TelegramCommand::factory()->create([
        'command' => 'notes',
        'message' => null,
        'learning_resource_id' => $resource->id,
    ]);

    postCustomCommand(600102, '/notes', 9202);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendDocument')
        && ($request->data()['document'] ?? null) === $fileId);

    expect(ResourceDownload::query()
        ->where('user_id', $user->id)
        ->where('learning_resource_id', $resource->id)
        ->exists())->toBeTrue();
});

it('locks a premium learning resource without sending its file', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 4]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $user = customCommandUser(600103);
    $course = $user->courses()->first();
    $fileId = 'BQACAgQAAxkBAAI-cmd-premium';

    $resource = LearningResource::factory()->published()->premium()->create([
        'title' => 'Premium Pack',
        'stream_id' => $user->stream_id,
        'course_id' => $course->id,
        'type' => ResourceType::Notes,
        'files' => null,
        'telegram_files' => [
            ['file_id' => $fileId, 'file_name' => 'premium.pdf'],
        ],
    ]);

    TelegramCommand::factory()->create([
        'command' => 'premiumpack',
        'message' => null,
        'learning_resource_id' => $resource->id,
    ]);

    postCustomCommand(600103, '/premiumpack', 9203);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'Premium Resource');
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
    expect(ResourceDownload::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('ignores inactive custom commands', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 5]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    customCommandUser(600104);

    TelegramCommand::factory()->inactive()->messageOnly('Should not send')->create([
        'command' => 'hidden',
    ]);

    postCustomCommand(600104, '/hidden', 9204);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return ! str_contains($text, 'Should not send')
            && str_contains($text, 'Choose');
    });
});

it('falls through for unknown slash commands', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 6]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    customCommandUser(600105);

    postCustomCommand(600105, '/doesnotexist', 9205);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'Choose');
    });
});

it('does not run custom commands during onboarding', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 7]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    User::factory()->student()->create([
        'telegram_id' => '600106',
        'onboarding_step' => OnboardingStep::Stream,
    ]);

    TelegramCommand::factory()->messageOnly('Onboarding should block this')->create([
        'command' => 'blocked',
    ]);

    postCustomCommand(600106, '/blocked', 9206);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return ! str_contains($text, 'Onboarding should block this');
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendDocument'));
});

it('keeps /start on the existing welcome path', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 8]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    customCommandUser(600107);

    TelegramCommand::factory()->messageOnly('Fake start hijack')->create([
        'command' => 'start_hijack',
    ]);

    postCustomCommand(600107, '/start', 9207);

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return ($request->data()['text'] ?? null) === 'Fake start hijack';
    });

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $text = (string) ($request->data()['text'] ?? '');

        return $text !== 'Fake start hijack'
            && $text !== '';
    });
});

it('matches commands with bot username suffix', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    customCommandUser(600108);

    TelegramCommand::factory()->messageOnly('Mention works')->create([
        'command' => 'helpdesk',
    ]);

    postCustomCommand(600108, '/helpdesk@noviquni_bot', 9208);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendMessage')
        && ($request->data()['text'] ?? null) === 'Mention works');
});
