<?php

use App\Enums\ReferralStatus;
use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows who referred whom on the referrals admin list', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $referrer = User::factory()->student()->create([
        'name' => 'Referrer Alice',
        'telegram_username' => 'alice_ref',
    ]);

    $referred = User::factory()->student()->create([
        'name' => 'Referred Bob',
        'telegram_username' => 'bob_ref',
        'referred_by_user_id' => $referrer->id,
    ]);

    Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referred_id' => $referred->id,
        'status' => ReferralStatus::Qualified,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListReferrals::class)
        ->assertCanSeeTableRecords(Referral::all())
        ->assertSee('Referrer Alice')
        ->assertSee('Referred Bob');
});

it('shows referred by on the student edit form', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $referrer = User::factory()->student()->create([
        'name' => 'Referrer Carol',
        'telegram_username' => 'carol_ref',
    ]);

    $referred = User::factory()->student()->create([
        'name' => 'Referred Dave',
        'referred_by_user_id' => $referrer->id,
    ]);

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.students.edit', ['record' => $referred]))
        ->assertOk()
        ->assertSee('Referrer Carol')
        ->assertSee('@carol_ref');
});
