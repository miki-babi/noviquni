<?php

namespace Database\Factories;

use App\Models\LearningResource;
use App\Models\StudyPlan;
use App\Models\StudyPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyPlanItem>
 */
class StudyPlanItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_plan_id' => StudyPlan::factory(),
            'learning_resource_id' => null,
            'label' => fake()->sentence(3),
            'sort_order' => 0,
        ];
    }

    public function forResource(?LearningResource $resource = null): static
    {
        return $this->state(function (array $attributes) use ($resource) {
            $resource ??= LearningResource::factory()->published()->create();

            return [
                'learning_resource_id' => $resource->id,
                'label' => $resource->title,
            ];
        });
    }
}
