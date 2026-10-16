<?php

namespace Database\Factories;

use App\Models\DailyChallenge;
use App\Models\DailyChallengeAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyChallengeAttempt>
 */
class DailyChallengeAttemptFactory extends Factory
{
    protected $model = DailyChallengeAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = 10;
        $score = fake()->numberBetween(0, $total);

        return [
            'user_id' => User::factory(),
            'daily_challenge_id' => DailyChallenge::factory(),
            'score' => $score,
            'total' => $total,
            'duration_ms' => fake()->numberBetween(30_000, 600_000),
            'completed_at' => now(),
        ];
    }
}
