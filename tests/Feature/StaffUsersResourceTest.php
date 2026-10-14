<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates staff users and assigns shield roles from the admin form', function () {
    $admin = User::factory()->admin()->create();
    $role = Role::findOrCreate('editor', 'web');

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Editor Staff',
            'email' => 'editor-staff@example.com',
            'password' => 'password',
            'is_active' => true,
            'roles' => [$role->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    $staff = User::query()->where('email', 'editor-staff@example.com')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->role)->toBe(UserRole::Admin)
        ->and($staff->is_active)->toBeTrue()
        ->and($staff->hasRole('editor'))->toBeTrue();
});
