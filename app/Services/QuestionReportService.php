<?php

namespace App\Services;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use App\Enums\ResourceType;
use App\Models\DailyChallenge;
use App\Models\LearningResource;
use App\Models\QuestionReport;
use App\Models\User;
use App\Support\DailyChallengeCalendar;
use App\Support\StudyRichText;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuestionReportService
{
    /**
     * @param  array{
     *     source: string,
     *     learning_resource_id?: int|null,
     *     daily_challenge_id?: int|null,
     *     question_index: int,
     *     reason: string,
     *     note?: string|null
     * }  $data
     */
    public function report(User $user, array $data): QuestionReport
    {
        $source = QuestionReportSource::from($data['source']);
        $reason = QuestionReportReason::from($data['reason']);
        $note = filled($data['note'] ?? null) ? trim((string) $data['note']) : null;
        $questionIndex = (int) $data['question_index'];

        if ($reason === QuestionReportReason::Other && ($note === null || mb_strlen($note) < 3)) {
            throw new HttpException(422, 'Please describe the issue when choosing Other.');
        }

        [$resource, $challenge, $question] = $this->resolveTarget(
            $source,
            isset($data['learning_resource_id']) ? (int) $data['learning_resource_id'] : null,
            isset($data['daily_challenge_id']) ? (int) $data['daily_challenge_id'] : null,
            $questionIndex,
        );

        if ($this->hasPendingDuplicate($user, $source, $resource, $challenge, $questionIndex)) {
            throw new HttpException(422, 'You already reported this question.');
        }

        $plain = StudyRichText::toPlain($question['question'] ?? '');
        $label = filled($plain) ? Str::limit($plain, 180) : 'Question '.($questionIndex + 1);
        $keySeed = mb_strtolower($plain).'|'.($resource?->id ?? 'daily:'.($challenge?->id ?? 0));

        return QuestionReport::query()->create([
            'user_id' => $user->id,
            'learning_resource_id' => $resource?->id,
            'daily_challenge_id' => $challenge?->id,
            'source' => $source,
            'question_index' => $questionIndex,
            'question_key' => hash('sha256', $keySeed),
            'question_label' => $label,
            'reason' => $reason,
            'note' => $note,
            'status' => QuestionReportStatus::Pending,
        ]);
    }

    public function resolve(QuestionReport $report, User $admin, ?string $adminNotes = null): QuestionReport
    {
        return $this->close($report, $admin, QuestionReportStatus::Resolved, $adminNotes);
    }

    public function dismiss(QuestionReport $report, User $admin, ?string $adminNotes = null): QuestionReport
    {
        return $this->close($report, $admin, QuestionReportStatus::Dismissed, $adminNotes);
    }

    protected function close(
        QuestionReport $report,
        User $admin,
        QuestionReportStatus $status,
        ?string $adminNotes = null,
    ): QuestionReport {
        if (! $report->isPending()) {
            throw new InvalidArgumentException('Only pending reports can be updated.');
        }

        $report->update([
            'status' => $status,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
            'admin_notes' => filled($adminNotes) ? trim($adminNotes) : $report->admin_notes,
        ]);

        return $report->fresh();
    }

    /**
     * @return array{0: ?LearningResource, 1: ?DailyChallenge, 2: array<string, mixed>}
     */
    protected function resolveTarget(
        QuestionReportSource $source,
        ?int $learningResourceId,
        ?int $dailyChallengeId,
        int $questionIndex,
    ): array {
        if ($source === QuestionReportSource::DailyChallenge) {
            $challenge = DailyChallenge::query()->find($dailyChallengeId);

            if ($challenge === null
                || $challenge->challenge_date->toDateString() !== DailyChallengeCalendar::todayDateString()
            ) {
                throw new HttpException(404, 'Daily challenge not found.');
            }

            $questions = $challenge->playerPayload()['questions'];
            $question = $questions[$questionIndex] ?? null;

            if (! is_array($question)) {
                throw new HttpException(422, 'That question is not available.');
            }

            return [null, $challenge, $question];
        }

        $resource = LearningResource::query()->find($learningResourceId);

        if ($resource === null || ! $resource->is_published) {
            throw new HttpException(404, 'Resource not found.');
        }

        $expectedType = $source === QuestionReportSource::Quiz
            ? ResourceType::Quiz
            : [ResourceType::PracticeExams, ResourceType::PastExams];

        if (is_array($expectedType)) {
            if (! in_array($resource->type, $expectedType, true)) {
                throw new HttpException(404, 'Resource not found.');
            }
        } elseif ($resource->type !== $expectedType) {
            throw new HttpException(404, 'Resource not found.');
        }

        $payload = $resource->playerPayload();
        $questions = is_array($payload['questions'] ?? null) ? array_values($payload['questions']) : [];
        $question = $questions[$questionIndex] ?? null;

        if (! is_array($question)) {
            throw new HttpException(422, 'That question is not available.');
        }

        return [$resource, null, $question];
    }

    protected function hasPendingDuplicate(
        User $user,
        QuestionReportSource $source,
        ?LearningResource $resource,
        ?DailyChallenge $challenge,
        int $questionIndex,
    ): bool {
        $query = QuestionReport::query()
            ->where('user_id', $user->id)
            ->where('question_index', $questionIndex)
            ->where('status', QuestionReportStatus::Pending);

        if ($source === QuestionReportSource::DailyChallenge) {
            $query->where('daily_challenge_id', $challenge?->id);
        } else {
            $query->where('learning_resource_id', $resource?->id);
        }

        return $query->exists();
    }
}
