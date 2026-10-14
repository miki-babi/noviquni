<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ArtisanCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows super admins to open the artisan page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->get('/admin/run-artisan-commands')
        ->assertOk();
});

it('denies staff without the super admin role from the artisan page', function () {
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
        ->get('/admin/run-artisan-commands')
        ->assertForbidden();
});

it('denies students access to the artisan page', function () {
    $student = User::factory()->student()->create([
        'email' => 'student@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($student)
        ->get('/admin/run-artisan-commands')
        ->assertForbidden();
});

it('runs a whitelisted artisan command', function () {
    $result = app(ArtisanCommandRunner::class)->run('about');

    expect($result['exit_code'])->toBe(0)
        ->and($result['command'])->toBe('about')
        ->and($result['output'])->not->toBeEmpty();
});

it('rejects commands that are not whitelisted', function () {
    app(ArtisanCommandRunner::class)->run('migrate:fresh --force');
})->throws(InvalidArgumentException::class);
