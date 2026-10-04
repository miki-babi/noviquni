<?php

use App\Enums\ChallengeRewardType;
use App\Enums\ReferralPointTransactionType;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\ReferralPointTransaction;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\ChallengeService;
use App\Services\ReferralPointService;
use App\Services\ReferralService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();

    config(['services.telegram.bot_token' => 'test-token']);

    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
    ]);
});

it('credits referral points instead of cash rewards', function () {
    $referrer = User::factory()->student()->create();
    $referred = User::factory()->student()->create();

    app(ReferralService::class)->attributeReferral($referred, $referrer->referral_code);

    $referrer->refresh();

    expect($referrer->referral_points)->toBe(1)
        ->and(ReferralReward::query()->count())->toBe(0)
        ->and(ReferralPointTransaction::query()->where('user_id', $referrer->id)->count())->toBe(1)
        ->and(ReferralPointTransaction::query()->first()->type)->toBe(ReferralPointTransactionType::ReferralEarned);
});

it('auto redeems a challenge when the user has enough points', function () {
    $user = User::factory()->student()->create(['referral_points' => 5]);

    $challenge = Challenge::factory()->create([
        'cost_points' => 5,
        'reward_type' => ChallengeRewardType::Message,
        'reward_message' => 'Scholarship mentorship unlocked.',
    ]);

    $completions = app(ChallengeService::class)->tryAutoRedeem($user);

    expect($completions)->toHaveCount(1)
        ->and($user->fresh()->referral_points)->toBe(0)
        ->and(ChallengeCompletion::query()->where('user_id', $user->id)->where('challenge_id', $challenge->id)->exists())->toBeTrue()
        ->and($challenge->fresh()->winners_count)->toBe(1);
});

it('does not redeem when points are insufficient', function () {
    $user = User::factory()->student()->create(['referral_points' => 2]);

    Challenge::factory()->create(['cost_points' => 5]);

    $completions = app(ChallengeService::class)->tryAutoRedeem($user);

    expect($completions)->toHaveCount(0)
        ->and($user->fresh()->referral_points)->toBe(2)
        ->and(ChallengeCompletion::query()->count())->toBe(0);
});

it('does not double complete the same challenge', function () {
    $user = User::factory()->student()->create(['referral_points' => 10]);

    $challenge = Challenge::factory()->create(['cost_points' => 5]);

    app(ChallengeService::class)->tryAutoRedeem($user);
    app(ChallengeService::class)->tryAutoRedeem($user->fresh());

    expect(ChallengeCompletion::query()->where('challenge_id', $challenge->id)->count())->toBe(1)
        ->and($user->fresh()->referral_points)->toBe(5);
});

it('respects max winners', function () {
    $challenge = Challenge::factory()->create([
        'cost_points' => 1,
        'max_winners' => 1,
    ]);

    $first = User::factory()->student()->create(['referral_points' => 1]);
    $second = User::factory()->student()->create(['referral_points' => 1]);

    app(ChallengeService::class)->tryAutoRedeem($first);
    app(ChallengeService::class)->tryAutoRedeem($second);

    expect(ChallengeCompletion::query()->where('challenge_id', $challenge->id)->count())->toBe(1)
        ->and($first->fresh()->referral_points)->toBe(0)
        ->and($second->fresh()->referral_points)->toBe(1);
});

it('grants premium days for premium challenge rewards', function () {
    $user = User::factory()->student()->create(['referral_points' => 3]);

    Challenge::factory()->premiumDays(14)->create(['cost_points' => 3]);

    app(ChallengeService::class)->tryAutoRedeem($user);

    expect($user->fresh()->hasActivePremium())->toBeTrue();
});

it('auto redeems after referral credits points', function () {
    $referrer = User::factory()->student()->create();

    Challenge::factory()->create([
        'cost_points' => 1,
        'reward_message' => 'First referral prize',
    ]);

    $referred = User::factory()->student()->create();
    app(ReferralService::class)->attributeReferral($referred, $referrer->referral_code);

    expect($referrer->fresh()->referral_points)->toBe(0)
        ->and(ChallengeCompletion::query()->where('user_id', $referrer->id)->count())->toBe(1);
});

it('allows admin point adjustments that trigger auto redeem', function () {
    $user = User::factory()->student()->create(['referral_points' => 0]);

    Challenge::factory()->create(['cost_points' => 2]);

    app(ReferralPointService::class)->credit(
        $user,
        2,
        ReferralPointTransactionType::AdminAdjustment,
    );

    app(ChallengeService::class)->tryAutoRedeem($user->fresh());

    expect($user->fresh()->referral_points)->toBe(0)
        ->and(ChallengeCompletion::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('lists only active challenges and skips draft ones for auto redeem', function () {
    $user = User::factory()->student()->create(['referral_points' => 10]);

    $active = Challenge::factory()->create([
        'cost_points' => 5,
        'reward_message' => 'Active prize',
    ]);

    $draft = Challenge::factory()->draft()->create([
        'cost_points' => 3,
        'reward_message' => 'Draft prize',
    ]);

    expect(Challenge::query()->listed()->pluck('id'))->toContain($active->id)
        ->and(Challenge::query()->listed()->pluck('id'))->not->toContain($draft->id)
        ->and(Challenge::query()->redeemable()->pluck('id'))->toContain($active->id)
        ->and(Challenge::query()->redeemable()->pluck('id'))->not->toContain($draft->id);

    $completions = app(ChallengeService::class)->tryAutoRedeem($user);

    expect($completions)->toHaveCount(1)
        ->and(ChallengeCompletion::query()->where('challenge_id', $active->id)->exists())->toBeTrue()
        ->and(ChallengeCompletion::query()->where('challenge_id', $draft->id)->exists())->toBeFalse()
        ->and($user->fresh()->referral_points)->toBe(5);
});
