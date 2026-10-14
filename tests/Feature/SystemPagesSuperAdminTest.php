<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows super admins to open system pages', function (string $path) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get($path)
        ->assertOk();
})->with([
    'settings' => '/admin/manage-settings',
    'activity logs' => '/admin/view-activity-logs',
    'artisan' => '/admin/run-artisan-commands',
]);

it('hides system pages from staff without the super admin role', function (string $path) {
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
        ->get($path)
        ->assertForbidden();
})->with([
    'settings' => '/admin/manage-settings',
    'activity logs' => '/admin/view-activity-logs',
    'artisan' => '/admin/run-artisan-commands',
]);
