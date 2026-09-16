<?php

namespace Database\Factories;

use App\Models\University;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<University>
 */
class UniversityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' University';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'location' => fake()->city().', Ethiopia',
            'website' => fake()->optional()->url(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_indexable' => true,
        ];
    }
}
