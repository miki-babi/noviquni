<?php

namespace Database\Factories;

use App\Enums\StudyPlanType;
use App\Models\Course;
use App\Models\StudyPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyPlan>
 */
class StudyPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'type' => StudyPlanType::Week,
            'title' => fake()->sentence(3),
            'week_number' => 1,
            'is_published' => false,
            'is_premium' => true,
            'description' => fake()->paragraph(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    public function baitChecklist(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StudyPlanType::BaitChecklist,
            'title' => 'Week-1 checklist',
            'week_number' => 1,
            'is_premium' => false,
        ]);
    }

    public function examSprint(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StudyPlanType::ExamSprint,
            'title' => 'Exam sprint',
            'week_number' => null,
            'is_premium' => true,
        ]);
    }
}
