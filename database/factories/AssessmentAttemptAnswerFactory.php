<?php

namespace Database\Factories;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssessmentAttemptAnswer>
 */
class AssessmentAttemptAnswerFactory extends Factory
{
    protected $model = AssessmentAttemptAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $correct = fake()->numberBetween(0, 3);
        $isCorrect = fake()->boolean(60);

        return [
            'assessment_attempt_id' => AssessmentAttempt::factory(),
            'question_index' => fake()->numberBetween(0, 5),
            'question_key' => hash('sha256', fake()->sentence()),
            'question_label' => Str::limit(fake()->sentence(), 120),
            'difficulty' => fake()->optional()->randomElement(['easy', 'medium', 'hard']),
            'topics' => ['Anthropology'],
            'selected_index' => $isCorrect ? $correct : (($correct + 1) % 4),
            'correct_index' => $correct,
            'is_correct' => $isCorrect,
        ];
    }

    public function correct(): static
    {
        return $this->state(function (array $attributes): array {
            $correct = (int) ($attributes['correct_index'] ?? 0);

            return [
                'selected_index' => $correct,
                'is_correct' => true,
            ];
        });
    }

    public function incorrect(): static
    {
        return $this->state(function (array $attributes): array {
            $correct = (int) ($attributes['correct_index'] ?? 0);

            return [
                'selected_index' => ($correct + 1) % 4,
                'is_correct' => false,
            ];
        });
    }
}
