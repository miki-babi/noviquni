<?php

use App\Enums\ReferralStatus;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows referral count and points on the students table', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $referrer = User::factory()->student()->create([
        'name' => 'Referrer Student',
        'referral_points' => 42,
    ]);

    $referredOne = User::factory()->student()->create();
    $referredTwo = User::factory()->student()->create();

    Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referred_id' => $referredOne->id,
        'status' => ReferralStatus::Qualified,
    ]);

    Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referred_id' => $referredTwo->id,
        'status' => ReferralStatus::Completed,
    ]);

    Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referred_id' => User::factory()->student()->create()->id,
        'status' => ReferralStatus::Pending,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords([$referrer])
        ->assertTableColumnStateSet('referrals_count', 2, $referrer)
        ->assertTableColumnStateSet('referral_points', 42, $referrer);
});
