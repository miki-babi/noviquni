<?php

use App\Enums\OnboardingStep;
use App\Http\Controllers\Telegram\MiniApp\HomeScreenController;
use App\Models\LearningResource;
use App\Models\User;
use App\Services\SettingsService;
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

it('increments the home screen prompt count up to three times', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'home_screen_prompt_count' => 0,
    ]);

    $this->actingAs($user);

    for ($i = 1; $i <= HomeScreenController::MaxPrompts; $i++) {
        $this->postJson(route('tg.home-screen.prompted'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'prompt_count' => $i,
                'added' => false,
            ]);

        expect($user->fresh()->home_screen_prompt_count)->toBe($i);
    }

    $this->postJson(route('tg.home-screen.prompted'))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'prompt_count' => HomeScreenController::MaxPrompts,
            'added' => false,
        ]);

    expect($user->fresh()->home_screen_prompt_count)->toBe(HomeScreenController::MaxPrompts);
});

it('marks the home screen as added and skips further prompt increments', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'home_screen_prompt_count' => 1,
    ]);

    $this->actingAs($user)
        ->postJson(route('tg.home-screen.added'))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'added' => true,
        ]);

    expect($user->fresh()->home_screen_added_at)->not->toBeNull();

    $this->actingAs($user)
        ->postJson(route('tg.home-screen.prompted'))
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'prompt_count' => 1,
            'added' => true,
        ]);

    expect($user->fresh()->home_screen_prompt_count)->toBe(1);
});

it('includes the home screen prompt markup for eligible users on profile edit', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'home_screen_prompt_count' => 0,
        'home_screen_added_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertSee('data-tg-home-screen-eligible', false)
        ->assertSee('data-tg-home-screen-modal', false)
        ->assertSee('Add to Home Screen', false)
        ->assertSee(route('tg.home-screen.prompted'), false);
});

it('omits the home screen prompt data after three prompts', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'home_screen_prompt_count' => HomeScreenController::MaxPrompts,
        'home_screen_added_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('tg.profile.edit'))
        ->assertOk()
        ->assertDontSee('data-tg-home-screen-eligible', false);
});

it('includes the home screen prompt on quiz players for eligible users', function () {
    $user = User::factory()->student()->create([
        'onboarding_step' => OnboardingStep::Complete,
        'is_active' => true,
        'home_screen_prompt_count' => 0,
    ]);

    $resource = LearningResource::factory()->published()->quiz()->create([
        'is_premium' => false,
    ]);

    $user->courses()->sync([$resource->course_id]);

    $this->actingAs($user)
        ->get(route('tg.play.quiz', $resource))
        ->assertOk()
        ->assertSee('data-tg-home-screen-eligible', false)
        ->assertSee('data-tg-home-screen-modal', false);
});
