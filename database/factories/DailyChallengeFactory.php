<?php

namespace Database\Factories;

use App\Models\DailyChallenge;
use App\Support\DailyChallengeCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyChallenge>
 */
class DailyChallengeFactory extends Factory
{
    protected $model = DailyChallenge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_date' => DailyChallengeCalendar::today()->toDateString(),
            'questions' => [
                $this->questionItem(1, 'maths', 0),
                $this->questionItem(1, 'maths', 1),
                $this->questionItem(1, 'maths', 2),
                $this->questionItem(1, 'maths', 3),
                $this->questionItem(2, 'reading-and-writing', 0),
                $this->questionItem(2, 'reading-and-writing', 1),
                $this->questionItem(2, 'reading-and-writing', 2),
                $this->questionItem(10, 'analytical-reasoning', 0),
                $this->questionItem(10, 'analytical-reasoning', 1),
                $this->questionItem(10, 'analytical-reasoning', 2),
            ],
        ];
    }

    /**
     * @return array{
     *     learning_resource_id: int,
     *     question_index: int,
     *     course_slug: string,
     *     ref: string,
     *     question: array<string, mixed>
     * }
     */
    protected function questionItem(int $resourceId, string $courseSlug, int $index): array
    {
        return [
            'learning_resource_id' => $resourceId,
            'question_index' => $index,
            'course_slug' => $courseSlug,
            'ref' => $resourceId.':'.$index,
            'question' => [
                'question' => "Sample question {$resourceId}-{$index}?",
                'options' => ['A', 'B', 'C', 'D'],
                'answerIndex' => 0,
                'explanation' => 'Because A is correct.',
            ],
        ];
    }
}
