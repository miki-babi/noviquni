<?php

namespace Database\Factories;

use App\Enums\YearSlug;
use App\Models\Year;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Year>
 */
class YearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->randomElement(YearSlug::cases());

        return [
            'name' => $slug->label(),
            'slug' => $slug->value.'-'.fake()->unique()->numerify('###'),
            'sort_order' => $slug->sortOrder(),
        ];
    }

    public function freshman(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => YearSlug::Freshman->label(),
            'slug' => YearSlug::Freshman->value,
            'sort_order' => YearSlug::Freshman->sortOrder(),
        ]);
    }
}
