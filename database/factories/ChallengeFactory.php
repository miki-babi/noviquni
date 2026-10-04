<?php

namespace Database\Factories;

use App\Enums\ChallengeRewardType;
use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'cost_points' => 5,
            'reward_type' => ChallengeRewardType::Message,
            'reward_value' => null,
            'reward_message' => 'You unlocked a scholarship mentorship session.',
            'status' => ChallengeStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
            'max_winners' => null,
            'winners_count' => 0,
            'created_by' => User::factory()->admin(),
        ];
    }

    public function premiumDays(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'reward_type' => ChallengeRewardType::PremiumDays,
            'reward_value' => $days,
            'reward_message' => "You earned {$days} days of premium.",
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChallengeStatus::Draft,
        ]);
    }
}
