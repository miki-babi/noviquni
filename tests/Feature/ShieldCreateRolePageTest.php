<?php

use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('loads the shield create role page for super admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Livewire::test(CreateRole::class)
        ->assertOk();
});
