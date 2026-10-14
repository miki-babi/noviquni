<?php

namespace Database\Factories;

use App\Enums\AssessmentKind;
use App\Models\AssessmentAttempt;
use App\Models\LearningResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentAttempt>
 */
class AssessmentAttemptFactory extends Factory
{
    protected $model = AssessmentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(2, 8);
        $score = fake()->numberBetween(0, $total);

        return [
            'user_id' => User::factory(),
            'learning_resource_id' => LearningResource::factory()->quiz(),
            'kind' => AssessmentKind::Quiz,
            'score' => $score,
            'total' => $total,
            'timed_out' => false,
            'completed_at' => now(),
        ];
    }

    public function exam(): static
    {
        return $this->state(fn (): array => [
            'kind' => AssessmentKind::Exam,
            'learning_resource_id' => LearningResource::factory()->exam(),
        ]);
    }
}
