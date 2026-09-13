<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningResource>
 */
class LearningResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $course = Course::factory()->create();

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(ResourceType::cases()),
            'course_id' => $course->id,
            'stream_id' => $course->stream_id,
            'is_premium' => false,
            'is_published' => false,
            'file_path' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }
}
