<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@noviquni.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'telegram_id' => null,
                'email_verified_at' => now(),
            ],
        );

        $role = Role::findOrCreate(
            (string) config('filament-shield.super_admin.name'),
            config('auth.defaults.guard') ?? 'web',
        );

        $admin->assignRole($role);
    }
}
