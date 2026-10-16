<?php

namespace Database\Factories;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use App\Models\DailyChallenge;
use App\Models\LearningResource;
use App\Models\QuestionReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionReport>
 */
class QuestionReportFactory extends Factory
{
    protected $model = QuestionReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'learning_resource_id' => LearningResource::factory()->quiz(),
            'daily_challenge_id' => null,
            'source' => QuestionReportSource::Quiz,
            'question_index' => 0,
            'question_key' => fake()->sha256(),
            'question_label' => fake()->sentence(6),
            'reason' => QuestionReportReason::Typo,
            'note' => null,
            'status' => QuestionReportStatus::Pending,
            'resolved_by' => null,
            'resolved_at' => null,
            'admin_notes' => null,
        ];
    }

    public function other(string $note = 'Something else is wrong'): static
    {
        return $this->state(fn (): array => [
            'reason' => QuestionReportReason::Other,
            'note' => $note,
        ]);
    }

    public function forDailyChallenge(?DailyChallenge $challenge = null): static
    {
        return $this->state(fn (): array => [
            'learning_resource_id' => null,
            'daily_challenge_id' => $challenge?->id ?? DailyChallenge::factory(),
            'source' => QuestionReportSource::DailyChallenge,
        ]);
    }

    public function forExam(?LearningResource $resource = null): static
    {
        return $this->state(fn (): array => [
            'learning_resource_id' => $resource?->id ?? LearningResource::factory()->exam(),
            'daily_challenge_id' => null,
            'source' => QuestionReportSource::Exam,
        ]);
    }

    public function resolved(?User $resolver = null): static
    {
        return $this->state(fn (): array => [
            'status' => QuestionReportStatus::Resolved,
            'resolved_by' => $resolver?->id ?? User::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(?User $resolver = null): static
    {
        return $this->state(fn (): array => [
            'status' => QuestionReportStatus::Dismissed,
            'resolved_by' => $resolver?->id ?? User::factory(),
            'resolved_at' => now(),
        ]);
    }
}
