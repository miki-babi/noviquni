<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults is_gat to false and casts it as boolean', function () {
    $user = User::factory()->student()->create();

    expect($user->is_gat)->toBeFalse()
        ->and($user->fresh()->is_gat)->toBeFalse();
});

it('can mark a student as GAT via the factory state', function () {
    $user = User::factory()->student()->gat()->create();

    expect($user->is_gat)->toBeTrue()
        ->and($user->fresh()->is_gat)->toBeTrue();
});
