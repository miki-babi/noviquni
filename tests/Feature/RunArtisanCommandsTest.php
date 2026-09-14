<?php

use App\Models\User;
use App\Services\ArtisanCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('allows admins to open the artisan page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->get('/admin/run-artisan-commands')
        ->assertOk();
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
