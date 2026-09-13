<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
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
        ->and($student->fresh()->hasActivePremium())->toBeFalse();

    app(PaymentService::class)->verify($payment, $admin);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Verified)
        ->and($student->fresh()->hasActivePremium())->toBeTrue()
        ->and(Payment::query()->where('user_id', $student->id)->where('status', PaymentStatus::Verified)->exists())->toBeTrue();
});
