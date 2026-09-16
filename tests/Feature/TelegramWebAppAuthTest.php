<?php

use App\Services\Telegram\TelegramWebAppAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use UnexpectedValueException;

uses(RefreshDatabase::class);

it('accepts a valid initData payload', function () {
    config(['services.telegram.bot_token' => '123456:TEST_TOKEN']);

    $initData = makeTelegramInitData([
        'id' => 555001,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN');

    $payload = app(TelegramWebAppAuth::class)->validate($initData);

    expect($payload['user']['id'])->toBe(555001)
        ->and($payload['auth_date'])->toBeInt();
});

it('rejects a tampered initData payload', function () {
    config(['services.telegram.bot_token' => '123456:TEST_TOKEN']);

    $initData = makeTelegramInitData([
        'id' => 555001,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN').'&user=%7B%22id%22%3A999%7D';

    app(TelegramWebAppAuth::class)->validate($initData);
})->throws(UnexpectedValueException::class);

it('rejects expired initData', function () {
    config(['services.telegram.bot_token' => '123456:TEST_TOKEN']);

    $initData = makeTelegramInitData([
        'id' => 555001,
        'first_name' => 'Abebe',
    ], '123456:TEST_TOKEN', now()->subDays(2)->timestamp);

    app(TelegramWebAppAuth::class)->validate($initData);
})->throws(UnexpectedValueException::class);
