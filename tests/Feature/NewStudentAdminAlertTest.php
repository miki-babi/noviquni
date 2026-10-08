<?php

use App\Jobs\SendTelegramMessageJob;
use App\Models\Stream;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.file_admin_username' => 'vault_admin',
    ]);
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

function newStudentStartPayload(int $telegramId, int $updateId = 1): array
{
    return [
        'update_id' => $updateId,
        'message' => [
            'message_id' => 10,
            'text' => '/start',
            'chat' => ['id' => $telegramId],
            'from' => [
                'id' => $telegramId,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'username' => 'jane_doe',
            ],
        ],
    ];
}

it('notifies the file admin when a new student is created', function () {
    Stream::factory()->create(['name' => 'Natural']);

    User::factory()->admin()->create([
        'telegram_id' => '900001',
        'telegram_username' => 'vault_admin',
    ]);

    User::factory()->student()->count(2)->create();

    Queue::fake([SendTelegramMessageJob::class]);

    $this->postJson('/telegram/webhook', newStudentStartPayload(555100))
        ->assertOk();

    Queue::assertNotPushed(SendTelegramMessageJob::class);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        if ((string) ($data['chat_id'] ?? '') !== '900001') {
            return false;
        }

        $text = (string) ($data['text'] ?? '');

        return str_contains($text, 'New student joined')
            && str_contains($text, 'Jane Doe')
            && str_contains($text, '@jane_doe')
            && str_contains($text, 'Total students: 3');
    });
});

it('does not notify the file admin when an existing student messages again', function () {
    Stream::factory()->create(['name' => 'Natural']);

    User::factory()->admin()->create([
        'telegram_id' => '900002',
        'telegram_username' => 'vault_admin',
    ]);

    User::factory()->student()->create([
        'telegram_id' => '555200',
        'telegram_username' => 'returning_student',
        'name' => 'Returning Student',
    ]);

    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 99]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);

    $this->postJson('/telegram/webhook', [
        'update_id' => 2,
        'message' => [
            'message_id' => 11,
            'text' => 'hello',
            'chat' => ['id' => 555200],
            'from' => [
                'id' => 555200,
                'first_name' => 'Returning',
                'username' => 'returning_student',
            ],
        ],
    ])->assertOk();

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return (string) ($request->data()['chat_id'] ?? '') === '900002'
            && str_contains((string) ($request->data()['text'] ?? ''), 'New student joined');
    });
});

it('skips admin alerts when the configured file admin username has no matching user', function () {
    Stream::factory()->create(['name' => 'Natural']);

    config(['services.telegram.file_admin_username' => 'unknown_admin']);

    $this->postJson('/telegram/webhook', newStudentStartPayload(555300))
        ->assertOk();

    Http::assertNotSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        return str_contains((string) ($request->data()['text'] ?? ''), 'New student joined');
    });
});
