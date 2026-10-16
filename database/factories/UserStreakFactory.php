<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserStreak;
use App\Support\DailyChallengeCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStreak>
 */
class UserStreakFactory extends Factory
{
    protected $model = UserStreak::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $current = fake()->numberBetween(0, 10);

        return [
            'user_id' => User::factory(),
            'current_streak' => $current,
            'longest_streak' => max($current, fake()->numberBetween(0, 30)),
            'last_completed_on' => DailyChallengeCalendar::today()->toDateString(),
            'badges' => [],
        ];
    }
}
