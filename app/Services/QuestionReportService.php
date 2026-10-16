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

    /**
     * Live question currently shown to students for this report, or null if unavailable.
     *
     * @return array<string, mixed>|null
     */
    public function liveQuestion(QuestionReport $report): ?array
    {
        try {
            [, , $question] = $this->resolveReportTarget($report);

            return is_array($question) ? $question : null;
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * Course id used for quiz-content image attachments when editing this report.
     */
    public function attachmentCourseId(QuestionReport $report): int|string|null
    {
        if ($report->learningResource !== null) {
            return $report->learningResource->course_id;
        }

        if ($report->dailyChallenge === null) {
            return null;
        }

        $items = array_values(is_array($report->dailyChallenge->questions) ? $report->dailyChallenge->questions : []);
        $item = $items[$report->question_index] ?? null;

        if (! is_array($item)) {
            return null;
        }

        $resourceId = $item['learning_resource_id'] ?? null;

        if (! is_numeric($resourceId)) {
            return null;
        }

        return LearningResource::query()->whereKey((int) $resourceId)->value('course_id');
    }

    /**
     * Replace the live question for this report and refresh the report snapshot label.
     *
     * @param  array{
     *     question: mixed,
     *     options: list<mixed>,
     *     answerIndex: int,
     *     explanation?: mixed
     * }  $question
     */
    public function updateQuestion(
        QuestionReport $report,
        array $question,
        ?User $admin = null,
        bool $markResolved = false,
        ?string $adminNotes = null,
    ): QuestionReport {
        [$resource, $challenge, $current] = $this->resolveReportTarget($report);

        if (! is_array($current)) {
            throw new InvalidArgumentException('That question is not available to edit.');
        }

        $normalized = [
            'question' => $question['question'],
            'options' => array_values($question['options']),
            'answerIndex' => (int) $question['answerIndex'],
        ];

        if (array_key_exists('explanation', $question) && ! StudyRichText::isEmpty($question['explanation'])) {
            $normalized['explanation'] = $question['explanation'];
        }

        if (filled($current['difficulty'] ?? null)) {
            $normalized['difficulty'] = (string) $current['difficulty'];
        }

        if ($resource !== null) {
            $this->replaceLearningResourceQuestion($resource, $report->question_index, $normalized);
        }

        if ($challenge !== null) {
            $this->replaceDailyChallengeQuestion($challenge, $report->question_index, $normalized);
        }

        $plain = StudyRichText::toPlain($normalized['question'] ?? '');
        $label = filled($plain) ? Str::limit($plain, 180) : 'Question '.($report->question_index + 1);
        $keySeed = mb_strtolower($plain).'|'.($resource?->id ?? 'daily:'.($challenge?->id ?? 0));

        $report->update([
            'question_label' => $label,
            'question_key' => hash('sha256', $keySeed),
        ]);

        $report = $report->fresh() ?? $report;

        if ($markResolved && $admin !== null && $report->isPending()) {
            return $this->resolve($report, $admin, $adminNotes);
        }

        if ($adminNotes !== null && filled(trim($adminNotes))) {
            $report->update(['admin_notes' => trim($adminNotes)]);

            return $report->fresh() ?? $report;
        }

        return $report;
    }

    /**
     * @return array{0: ?LearningResource, 1: ?DailyChallenge, 2: array<string, mixed>}
     */
    protected function resolveReportTarget(QuestionReport $report): array
    {
        if ($report->source === QuestionReportSource::DailyChallenge) {
            $challenge = $report->dailyChallenge ?? DailyChallenge::query()->find($report->daily_challenge_id);

            if ($challenge === null) {
                throw new HttpException(404, 'Daily challenge not found.');
            }

            $questions = $challenge->playerPayload()['questions'];
            $question = $questions[$report->question_index] ?? null;

            if (! is_array($question)) {
                throw new HttpException(422, 'That question is not available.');
            }

            return [null, $challenge, $question];
        }

        $resource = $report->learningResource ?? LearningResource::query()->find($report->learning_resource_id);

        if ($resource === null) {
            throw new HttpException(404, 'Resource not found.');
        }

        $payload = $resource->playerPayload();
        $questions = is_array($payload['questions'] ?? null) ? array_values($payload['questions']) : [];
        $question = $questions[$report->question_index] ?? null;

        if (! is_array($question)) {
            throw new HttpException(422, 'That question is not available.');
        }

        return [$resource, null, $question];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function replaceLearningResourceQuestion(
        LearningResource $resource,
        int $questionIndex,
        array $question,
    ): void {
        $payload = $resource->playerPayload();
        $questions = is_array($payload['questions'] ?? null) ? array_values($payload['questions']) : [];

        if (! array_key_exists($questionIndex, $questions)) {
            throw new InvalidArgumentException('That question is not available to edit.');
        }

        $questions[$questionIndex] = $question;

        $content = is_array($resource->content) ? $resource->content : [];
        $existingPayload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        unset($existingPayload['partA']);

        $content['payload'] = [
            ...$existingPayload,
            'questions' => $questions,
        ];

        if (isset($payload['title'])) {
            $content['payload']['title'] = $payload['title'];
        }

        if (isset($payload['instructions'])) {
            $content['payload']['instructions'] = $payload['instructions'];
        }

        if (isset($payload['timeLimitMinutes'])) {
            $content['payload']['timeLimitMinutes'] = $payload['timeLimitMinutes'];
        }

        $resource->update(['content' => $content]);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    protected function replaceDailyChallengeQuestion(
        DailyChallenge $challenge,
        int $questionIndex,
        array $question,
    ): void {
        $items = array_values(is_array($challenge->questions) ? $challenge->questions : []);

        if (! array_key_exists($questionIndex, $items) || ! is_array($items[$questionIndex])) {
            throw new InvalidArgumentException('That question is not available to edit.');
        }

        $item = $items[$questionIndex];

        if (is_array($item['question'] ?? null)) {
            $item['question'] = $question;
        } else {
            $item = [
                ...$item,
                ...$question,
            ];
        }

        $items[$questionIndex] = $item;
        $challenge->update(['questions' => $items]);

        $sourceResourceId = $item['learning_resource_id'] ?? null;
        $sourceQuestionIndex = $item['question_index'] ?? null;

        if (! is_numeric($sourceResourceId) || ! is_numeric($sourceQuestionIndex)) {
            return;
        }

        $source = LearningResource::query()->find((int) $sourceResourceId);

        if ($source === null) {
            return;
        }

        try {
            $this->replaceLearningResourceQuestion($source, (int) $sourceQuestionIndex, $question);
        } catch (InvalidArgumentException) {
            // Challenge snapshot still updated even if the catalog index moved.
        }
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
