<?php

use App\Enums\OnboardingStep;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\TelegramLottie;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
    config([
        'services.telegram.bot_token' => '123456:TEST_TOKEN',
        'services.telegram.bot_username' => 'noviquni_bot',
        'app.url' => 'https://noviquni.test',
    ]);
});

it('renders lottie markup when the asset file is present', function () {
    expect(TelegramLottie::exists('empty-courses'))->toBeTrue()
        ->and(TelegramLottie::exists('loading'))->toBeTrue()
        ->and(TelegramLottie::exists('hub-notes'))->toBeTrue();

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertSee('data-lottie-src', false)
        ->assertSee('girl-with-books.json', false);
});

it('omits lottie markup only when the mapped asset is missing', function () {
    config(['telegram.lottie.empty-courses' => 'does-not-exist.json']);

    expect(TelegramLottie::exists('empty-courses'))->toBeFalse();

    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tg.browse'))
        ->assertOk()
        ->assertDontSee('data-lottie-src', false);
});
