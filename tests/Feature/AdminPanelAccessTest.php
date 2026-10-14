<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows admins with a shield role to access the admin panel', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('allows staff with a limited shield role to access the admin panel', function () {
    $role = Role::findOrCreate('editor', 'web');

    $staff = User::factory()->create([
        'role' => UserRole::Admin,
        'email' => 'editor@example.com',
        'password' => 'password',
        'telegram_id' => null,
        'is_active' => true,
    ]);

    $staff->assignRole($role);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk();
});

it('denies users with the admin enum role but no shield role', function () {
    $adminWithoutShield = User::factory()->create([
        'role' => UserRole::Admin,
        'email' => 'legacy-admin@example.com',
        'password' => 'password',
        'telegram_id' => null,
        'is_active' => true,
    ]);

    expect($adminWithoutShield->roles()->exists())->toBeFalse();

    $this->actingAs($adminWithoutShield)
        ->get('/admin')
        ->assertForbidden();
});

it('denies students access to the admin panel', function () {
    $student = User::factory()->student()->create([
        'email' => 'student@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($student)
        ->get('/admin')
        ->assertForbidden();
});

it('redirects guests to the admin login', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});
