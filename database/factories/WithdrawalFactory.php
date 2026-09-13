<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'amount' => fake()->randomFloat(2, 10, 100),
            'method' => 'telebirr',
            'details' => fake()->phoneNumber(),
            'status' => WithdrawalStatus::Pending,
        ];
    }
}
