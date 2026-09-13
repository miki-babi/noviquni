<?php

use App\Enums\BroadcastButtonType;
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

it('returns null when buttons are empty', function () {
    expect(app(BroadcastService::class)->inlineKeyboard([]))->toBeNull()
        ->and(app(BroadcastService::class)->inlineKeyboard(null))->toBeNull();
});
