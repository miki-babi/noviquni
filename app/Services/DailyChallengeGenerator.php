<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\DailyChallenge;
use App\Models\LearningResource;
use App\Support\DailyChallengeCalendar;
use App\Support\StudyRichText;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class DailyChallengeGenerator
{
    /**
     * Subject quotas for each daily challenge.
     *
     * @var array<string, int>
     */
    public const array SubjectQuotas = [
        'maths' => 4,
        'reading-and-writing' => 3,
        'analytical-reasoning' => 3,
    ];

    public const int LookbackDays = 30;

    /**
     * @return array{challenge: DailyChallenge, created: bool}
     */
    public function generate(?CarbonInterface $at = null, bool $force = false): array
    {
        $date = DailyChallengeCalendar::today($at);
        $dateString = $date->toDateString();

        $existing = DailyChallenge::query()
            ->whereDate('challenge_date', $dateString)
            ->first();

        if ($existing !== null && ! $force) {
            return ['challenge' => $existing, 'created' => false];
        }

        $questions = $this->pickQuestions($date);

        if ($existing !== null) {
            $existing->update(['questions' => $questions]);

            return ['challenge' => $existing->fresh(), 'created' => false];
        }

        $challenge = DailyChallenge::query()->create([
            'challenge_date' => $dateString,
            'questions' => $questions,
        ]);

        return ['challenge' => $challenge, 'created' => true];
    }

    /**
     * @return list<array{
     *     learning_resource_id: int,
     *     question_index: int,
     *     course_slug: string,
     *     ref: string,
     *     question: array<string, mixed>
     * }>
     */
    public function pickQuestions(CarbonInterface $date): array
    {
        $excluded = $this->recentlyUsedRefs($date);
        $picked = [];

        foreach (self::SubjectQuotas as $courseSlug => $quota) {
            $candidates = $this->candidatesForCourse($courseSlug)
                ->reject(fn (array $item): bool => isset($excluded[$item['ref']]))
                ->values();

            if ($candidates->count() < $quota) {
                throw new RuntimeException(
                    "Not enough unused {$courseSlug} questions for daily challenge (need {$quota}, have {$candidates->count()})."
                );
            }

            $selected = $this->selectWithDifficultyMix($candidates, $quota);

            foreach ($selected as $item) {
                $picked[] = $item;
                $excluded[$item['ref']] = true;
            }
        }

        return collect($picked)->shuffle()->values()->all();
    }

    /**
     * @return array<string, true>
     */
    protected function recentlyUsedRefs(CarbonInterface $date): array
    {
        $from = $date->copy()->subDays(self::LookbackDays)->toDateString();
        $to = $date->copy()->subDay()->toDateString();

        $refs = [];

        DailyChallenge::query()
            ->whereDate('challenge_date', '>=', $from)
            ->whereDate('challenge_date', '<=', $to)
            ->orderBy('challenge_date')
            ->each(function (DailyChallenge $challenge) use (&$refs): void {
                foreach ($challenge->questions ?? [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $ref = $item['ref'] ?? null;

                    if (is_string($ref) && $ref !== '') {
                        $refs[$ref] = true;
                    }
                }
            });

        return $refs;
    }

    /**
     * @return Collection<int, array{
     *     learning_resource_id: int,
     *     question_index: int,
     *     course_slug: string,
     *     ref: string,
     *     question: array<string, mixed>
     * }>
     */
    protected function candidatesForCourse(string $courseSlug): Collection
    {
        $course = Course::query()
            ->where('slug', $courseSlug)
            ->first();

        if ($course === null) {
            return collect();
        }

        $candidates = collect();

        LearningResource::query()
            ->where('course_id', $course->id)
            ->where('type', ResourceType::Quiz)
            ->where('is_published', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (LearningResource $resource) use ($candidates, $courseSlug): void {
                $payload = $resource->playerPayload();
                $questions = is_array($payload['questions'] ?? null) ? $payload['questions'] : [];

                foreach (array_values($questions) as $index => $question) {
                    if (! is_array($question)
                        || StudyRichText::isEmpty($question['question'] ?? null)
                        || ! is_array($question['options'] ?? null)
                        || ($question['options'] ?? []) === []
                    ) {
                        continue;
                    }

                    $candidates->push([
                        'learning_resource_id' => $resource->id,
                        'question_index' => $index,
                        'course_slug' => $courseSlug,
                        'ref' => $resource->id.':'.$index,
                        'question' => $question,
                    ]);
                }
            });

        return $candidates;
    }

    /**
     * @param  Collection<int, array{
     *     learning_resource_id: int,
     *     question_index: int,
     *     course_slug: string,
     *     ref: string,
     *     question: array<string, mixed>
     * }>  $candidates
     * @return list<array{
     *     learning_resource_id: int,
     *     question_index: int,
     *     course_slug: string,
     *     ref: string,
     *     question: array<string, mixed>
     * }>
     */
    protected function selectWithDifficultyMix(Collection $candidates, int $quota): array
    {
        $withDifficulty = $candidates->filter(
            fn (array $item): bool => filled($item['question']['difficulty'] ?? null)
        );

        if ($withDifficulty->count() < $quota) {
            return $candidates->shuffle()->take($quota)->values()->all();
        }

        $grouped = $withDifficulty->groupBy(
            fn (array $item): string => Str::lower((string) $item['question']['difficulty'])
        );

        $selected = collect();
        $buckets = $grouped->keys()->shuffle()->values();

        while ($selected->count() < $quota && $buckets->isNotEmpty()) {
            foreach ($buckets as $bucket) {
                if ($selected->count() >= $quota) {
                    break;
                }

                $pool = $grouped->get($bucket);

                if (! $pool instanceof Collection || $pool->isEmpty()) {
                    continue;
                }

                $pick = $pool->shuffle()->first();
                $selected->push($pick);
                $grouped[$bucket] = $pool->reject(
                    fn (array $item): bool => $item['ref'] === $pick['ref']
                )->values();
            }

            $buckets = $grouped
                ->filter(fn (Collection $pool): bool => $pool->isNotEmpty())
                ->keys()
                ->shuffle()
                ->values();
        }

        if ($selected->count() < $quota) {
            $remaining = $candidates
                ->reject(fn (array $item): bool => $selected->contains(
                    fn (array $picked): bool => $picked['ref'] === $item['ref']
                ))
                ->shuffle()
                ->take($quota - $selected->count());

            $selected = $selected->concat($remaining);
        }

        return $selected->take($quota)->values()->all();
    }
}
