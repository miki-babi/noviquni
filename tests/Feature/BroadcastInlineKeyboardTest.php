<?php

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
use App\Models\Broadcast;
use App\Services\BroadcastService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('builds callback command buttons', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Get Premium',
            'type' => BroadcastButtonType::Command->value,
            'command' => 'premium_pay',
        ],
    ]);

    expect($markup)->toBe([
        'inline_keyboard' => [[
            [
                'text' => 'Get Premium',
                'callback_data' => 'premium_pay',
            ],
        ]],
    ]);
});

it('builds url and mini app buttons', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Open site',
            'type' => BroadcastButtonType::Url->value,
            'url' => 'https://example.com',
        ],
        [
            'label' => 'Open app',
            'type' => BroadcastButtonType::MiniApp->value,
            'url' => 'https://mini.example.com',
        ],
    ]);

    expect($markup['inline_keyboard'])->toHaveCount(2)
        ->and($markup['inline_keyboard'][0][0])->toMatchArray([
            'text' => 'Open site',
            'url' => 'https://example.com',
        ])
        ->and($markup['inline_keyboard'][1][0])->toMatchArray([
            'text' => 'Open app',
            'web_app' => ['url' => 'https://mini.example.com'],
        ]);
});

it('passes through button style when set', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Get Premium',
            'type' => BroadcastButtonType::Command->value,
            'command' => 'premium_pay',
            'style' => TelegramButtonStyle::Primary->value,
        ],
        [
            'label' => 'Open site',
            'type' => BroadcastButtonType::Url->value,
            'url' => 'https://example.com',
            'style' => TelegramButtonStyle::Success->value,
        ],
    ]);

    expect($markup['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Get Premium',
        'callback_data' => 'premium_pay',
        'style' => 'primary',
    ])
        ->and($markup['inline_keyboard'][1][0])->toMatchArray([
            'text' => 'Open site',
            'url' => 'https://example.com',
            'style' => 'success',
        ]);
});

it('omits invalid or blank button styles', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Neutral',
            'type' => BroadcastButtonType::Command->value,
            'command' => 'premium_pay',
            'style' => '',
        ],
        [
            'label' => 'Also neutral',
            'type' => BroadcastButtonType::Command->value,
            'command' => 'premium_pay',
            'style' => 'not-a-style',
        ],
    ]);

    expect($markup['inline_keyboard'][0][0])->not->toHaveKey('style')
        ->and($markup['inline_keyboard'][1][0])->not->toHaveKey('style');
});

it('returns null when buttons are empty', function () {
    expect(app(BroadcastService::class)->inlineKeyboard([]))->toBeNull()
        ->and(app(BroadcastService::class)->inlineKeyboard(null))->toBeNull();
});

it('builds text reply buttons using the label as callback payload', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => '⭐ Premium',
            'type' => BroadcastButtonType::Text->value,
        ],
    ]);

    expect($markup['inline_keyboard'][0][0])->toMatchArray([
        'text' => '⭐ Premium',
        'callback_data' => 'reply:⭐ Premium',
    ]);
});

it('builds text reply buttons with optional reply text override', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Get help',
            'type' => BroadcastButtonType::Text->value,
            'command' => '/help',
        ],
    ]);

    expect($markup['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Get help',
        'callback_data' => 'reply:/help',
    ]);
});

it('passes through style on text reply buttons', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Courses',
            'type' => BroadcastButtonType::Text->value,
            'style' => TelegramButtonStyle::Primary->value,
        ],
    ]);

    expect($markup['inline_keyboard'][0][0])->toMatchArray([
        'text' => 'Courses',
        'callback_data' => 'reply:Courses',
        'style' => 'primary',
    ]);
});

it('builds choice buttons side by side with broadcast-scoped callbacks', function () {
    $broadcast = Broadcast::factory()->create();

    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => "I'm in",
            'type' => BroadcastButtonType::Choice->value,
            'response' => 'Great, see you there!',
            'style' => TelegramButtonStyle::Success->value,
        ],
        [
            'label' => 'Not now',
            'type' => BroadcastButtonType::Choice->value,
            'response' => 'No problem — maybe next time.',
            'style' => TelegramButtonStyle::Danger->value,
        ],
        [
            'label' => 'Open site',
            'type' => BroadcastButtonType::Url->value,
            'url' => 'https://example.com',
        ],
    ], $broadcast);

    expect($markup['inline_keyboard'])->toHaveCount(2)
        ->and($markup['inline_keyboard'][0])->toHaveCount(2)
        ->and($markup['inline_keyboard'][0][0])->toMatchArray([
            'text' => "I'm in",
            'callback_data' => 'bcq:'.$broadcast->id.':0',
            'style' => 'success',
        ])
        ->and($markup['inline_keyboard'][0][1])->toMatchArray([
            'text' => 'Not now',
            'callback_data' => 'bcq:'.$broadcast->id.':1',
            'style' => 'danger',
        ])
        ->and($markup['inline_keyboard'][1][0])->toMatchArray([
            'text' => 'Open site',
            'url' => 'https://example.com',
        ]);
});

it('omits choice buttons when no broadcast is provided', function () {
    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Yes',
            'type' => BroadcastButtonType::Choice->value,
            'response' => 'Thanks!',
        ],
        [
            'label' => 'Open site',
            'type' => BroadcastButtonType::Url->value,
            'url' => 'https://example.com',
        ],
    ]);

    expect($markup['inline_keyboard'])->toHaveCount(1)
        ->and($markup['inline_keyboard'][0][0])->toMatchArray([
            'text' => 'Open site',
            'url' => 'https://example.com',
        ]);
});

it('omits choice buttons without a response message', function () {
    $broadcast = Broadcast::factory()->create();

    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Yes',
            'type' => BroadcastButtonType::Choice->value,
            'response' => '',
        ],
    ], $broadcast);

    expect($markup)->toBeNull();
});

it('builds choice buttons that run a telegram command', function () {
    $broadcast = Broadcast::factory()->create();

    $markup = app(BroadcastService::class)->inlineKeyboard([
        [
            'label' => 'Get syllabus',
            'type' => BroadcastButtonType::Choice->value,
            'action' => 'command',
            'command' => 'syllabus',
        ],
        [
            'label' => 'Not now',
            'type' => BroadcastButtonType::Choice->value,
            'action' => 'message',
            'response' => 'Maybe later.',
        ],
    ], $broadcast);

    expect($markup['inline_keyboard'][0])->toHaveCount(2)
        ->and($markup['inline_keyboard'][0][0])->toMatchArray([
            'text' => 'Get syllabus',
            'callback_data' => 'bcq:'.$broadcast->id.':0',
        ])
        ->and($markup['inline_keyboard'][0][1])->toMatchArray([
            'text' => 'Not now',
            'callback_data' => 'bcq:'.$broadcast->id.':1',
        ]);
});
