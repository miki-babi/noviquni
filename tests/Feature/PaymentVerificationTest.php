<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionSource;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PremiumService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('activates premium when an admin verifies a payment', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $payment = app(PaymentService::class)->createPendingPremiumPayment($student);

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($student->fresh()->hasActivePremium())->toBeFalse()
        ->and($student->fresh()->is_premium)->toBeFalse();

    app(PaymentService::class)->verify($payment, $admin);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Verified)
        ->and($student->fresh()->hasActivePremium())->toBeTrue()
        ->and($student->fresh()->is_premium)->toBeTrue()
        ->and(Payment::query()->where('user_id', $student->id)->where('status', PaymentStatus::Verified)->exists())->toBeTrue();
});

it('grants and revokes permanent premium via the boolean flag', function () {
    $student = User::factory()->student()->create(['is_premium' => false]);

    app(PremiumService::class)->grant($student, SubscriptionSource::Admin);

    expect($student->fresh()->is_premium)->toBeTrue()
        ->and($student->fresh()->hasActivePremium())->toBeTrue()
        ->and(Subscription::query()->where('user_id', $student->id)->whereNull('ends_at')->exists())->toBeTrue();

    app(PremiumService::class)->revoke($student);

    expect($student->fresh()->is_premium)->toBeFalse()
        ->and($student->fresh()->hasActivePremium())->toBeFalse();
});
