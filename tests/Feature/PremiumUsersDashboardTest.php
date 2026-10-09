<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionSource;
use App\Filament\Resources\PremiumUsers\Pages\ManagePremiumUsers;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PremiumService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SettingsService::class)->seedDefaults();
});

it('lists only premium students on the premium users dashboard', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $premium = User::factory()->student()->create([
        'name' => 'Premium Student',
        'is_premium' => true,
    ]);
    $regular = User::factory()->student()->create([
        'name' => 'Regular Student',
        'is_premium' => false,
    ]);

    $this->actingAs($admin);

    Livewire::test(ManagePremiumUsers::class)
        ->assertCanSeeTableRecords([$premium])
        ->assertCanNotSeeTableRecords([$regular]);
});

it('reverts a verified payment from the premium users dashboard', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Paid Student',
        'is_premium' => false,
    ]);

    $payment = app(PaymentService::class)->createPendingPremiumPayment($student);
    app(PaymentService::class)->verify($payment, $admin);

    expect($student->fresh()->is_premium)->toBeTrue();

    $this->actingAs($admin);

    Livewire::test(ManagePremiumUsers::class)
        ->assertCanSeeTableRecords([$student->fresh()])
        ->callTableAction('revertPayment', $student->fresh())
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($student->fresh()->is_premium)->toBeFalse();
});

it('revokes premium without a payment from the premium users dashboard', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $student = User::factory()->student()->create([
        'name' => 'Granted Student',
        'is_premium' => false,
    ]);

    app(PremiumService::class)->grant($student, SubscriptionSource::Admin);

    $this->actingAs($admin);

    Livewire::test(ManagePremiumUsers::class)
        ->callTableAction('revokePremium', $student->fresh())
        ->assertHasNoTableActionErrors();

    expect($student->fresh()->is_premium)->toBeFalse()
        ->and(Payment::query()->where('user_id', $student->id)->exists())->toBeFalse();
});
