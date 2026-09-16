<?php

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
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
