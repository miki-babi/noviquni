<?php

use App\Enums\BroadcastButtonType;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => 'test-token',
    ]);
    Http::fake([
        'api.telegram.org/bot*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 501]], 200),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
    ]);
});

it('sends a personalized telegram message to a specific student', function () {
    $student = User::factory()->student()->create([
        'name' => 'Abebe Kebede',
        'telegram_id' => '777100',
        'referral_points' => 12,
    ]);

    $sent = app(BroadcastService::class)->sendToUser(
        $student,
        'Hi {{first_name}}, you have {{referral_points}} points.',
        [
            [
                'label' => 'Open Premium',
                'type' => BroadcastButtonType::Text->value,
                'command' => '⭐ Premium',
            ],
        ],
    );

    expect($sent)->toBeTrue();

    $delivery = NotificationDelivery::query()->where('user_id', $student->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->broadcast_id)->toBeNull()
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->body)->toBe('Hi Abebe, you have 12 points.')
        ->and($delivery->telegram_message_id)->toBe(501);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return (string) ($data['chat_id'] ?? '') === '777100'
            && ($data['text'] ?? '') === 'Hi Abebe, you have 12 points.'
            && data_get($data, 'reply_markup.inline_keyboard.0.0.callback_data') === 'reply:⭐ Premium';
    });
});

it('returns false when the student has no telegram id', function () {
    $student = User::factory()->student()->create([
        'telegram_id' => null,
    ]);

    $sent = app(BroadcastService::class)->sendToUser($student, 'Hello');

    expect($sent)->toBeFalse()
        ->and(NotificationDelivery::query()->count())->toBe(0);
});

it('sends a telegram message from the student edit page action', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Direct Student',
        'telegram_id' => '777200',
    ]);

    $this->actingAs($admin);

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->callAction('sendTelegramMessage', [
            'body' => 'Hello {{first_name}}',
            'buttons' => [],
        ])
        ->assertNotified();

    expect(NotificationDelivery::query()->where('user_id', $student->id)->exists())->toBeTrue();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMessage')) {
            return false;
        }

        $data = $request->data();

        return (string) ($data['chat_id'] ?? '') === '777200'
            && ($data['text'] ?? '') === 'Hello Direct';
    });
});
