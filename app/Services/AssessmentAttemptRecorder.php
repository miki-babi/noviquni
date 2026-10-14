<?php

namespace App\Services;

use App\Enums\AssessmentKind;
use App\Enums\CollegeResourceKind;
use App\Enums\UserEventName;
use App\Models\AssessmentAttempt;
use App\Models\LearningResource;
use App\Models\User;
use App\Support\StudyRichText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssessmentAttemptRecorder
{
    public function __construct(
        protected UserEventService $userEvents,
    ) {}

    /**
     * @param  array<int|string, mixed>  $answers  question index => selected option index (or null if skipped)
     */
    public function record(
        User $user,
        LearningResource $resource,
        AssessmentKind $kind,
        array $answers,
        bool $timedOut = false,
    ): AssessmentAttempt {
        $questions = $this->questionsForResource($resource, $kind);

        if ($questions === []) {
            throw new InvalidArgumentException('This resource has no scorable questions.');
        }

        $topics = collect($resource->topics ?? [])
            ->filter(fn ($topic): bool => is_string($topic) && filled($topic))
            ->values()
            ->all();

        $answerRows = [];
        $score = 0;

        foreach ($questions as $index => $question) {
            $correctIndex = (int) ($question['answerIndex'] ?? 0);
            $rawSelected = $answers[$index] ?? $answers[(string) $index] ?? null;
            $selectedIndex = is_numeric($rawSelected) ? (int) $rawSelected : null;
            $isCorrect = $selectedIndex !== null && $selectedIndex === $correctIndex;

            if ($isCorrect) {
                $score++;
            }

            $plain = StudyRichText::toPlain($question['question'] ?? '');
            $label = filled($plain) ? Str::limit($plain, 120) : 'Question '.($index + 1);

            $answerRows[] = [
                'question_index' => $index,
                'question_key' => hash('sha256', mb_strtolower($plain).'|'.$resource->id),
                'question_label' => $label,
                'difficulty' => filled($question['difficulty'] ?? null)
                    ? Str::lower((string) $question['difficulty'])
                    : null,
                'topics' => $topics === [] ? null : $topics,
                'selected_index' => $selectedIndex,
                'correct_index' => $correctIndex,
                'is_correct' => $isCorrect,
            ];
        }

        $total = count($questions);

        return DB::transaction(function () use ($user, $resource, $kind, $score, $total, $timedOut, $answerRows): AssessmentAttempt {
            $attempt = AssessmentAttempt::query()->create([
                'user_id' => $user->id,
                'learning_resource_id' => $resource->id,
                'kind' => $kind,
                'score' => $score,
                'total' => $total,
                'timed_out' => $timedOut,
                'completed_at' => now(),
            ]);

            $attempt->answers()->createMany($answerRows);

            $event = $kind === AssessmentKind::Exam
                ? UserEventName::ExamDone
                : UserEventName::QuizDone;

            $this->userEvents->log($user, $event, $this->userEvents->resourceStudyDetails($resource, [
                'score' => $score,
                'total' => $total,
                'attempt_id' => $attempt->id,
                'timed_out' => $timedOut,
            ]));

            return $attempt->load('answers');
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questionsForResource(LearningResource $resource, AssessmentKind $kind): array
    {
        $payload = $resource->playerPayload();

        if (! is_array($payload)) {
            return [];
        }

        if ($kind === AssessmentKind::Exam || $resource->studyKind() === CollegeResourceKind::Exam) {
            $normalized = $resource->normalizeExam(
                is_array($resource->content['payload'] ?? null)
                    ? $resource->content['payload']
                    : $payload,
            );
            $questions = $normalized['questions'] ?? [];
        } else {
            $questions = $payload['questions'] ?? [];
        }

        return array_values(array_filter(
            is_array($questions) ? $questions : [],
            fn ($question): bool => is_array($question)
                && ! StudyRichText::isEmpty($question['question'] ?? null)
                && is_array($question['options'] ?? null)
                && ($question['options'] ?? []) !== [],
        ));
    }
}
