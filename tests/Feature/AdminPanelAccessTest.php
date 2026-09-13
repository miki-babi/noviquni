<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to access the admin panel', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
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
