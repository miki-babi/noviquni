<?php

namespace Database\Factories;

use App\Enums\OpportunityType;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(OpportunityType::cases()),
            'description' => fake()->paragraph(),
            'url' => fake()->url(),
            'deadline' => fake()->optional()->dateTimeBetween('+1 week', '+6 months'),
            'is_published' => false,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }

    public function scholarship(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => OpportunityType::Scholarship,
        ]);
    }

    public function internship(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => OpportunityType::Internship,
        ]);
    }

    public function job(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => OpportunityType::Job,
        ]);
    }

    public function mentorship(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => OpportunityType::Mentorship,
        ]);
    }
}
