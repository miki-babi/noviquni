<?php

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeCompletion>
 */
class ChallengeCompletionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'user_id' => User::factory()->student(),
            'points_spent' => 5,
            'completed_at' => now(),
        ];
    }
}
