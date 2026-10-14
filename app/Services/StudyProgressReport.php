<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptAnswer;
use App\Models\User;
use Illuminate\Support\Collection;

class StudyProgressReport
{
    /**
     * @return array{
     *     summary: array{attempts: int, average_percent: float, latest_percent: ?float, trend: string},
     *     strengths: list<array{label: string, accuracy: float, attempts: int}>,
     *     weaknesses: list<array{label: string, accuracy: float, attempts: int, focus: string}>,
     *     focus: list<string>,
     *     timeline: list<array{date: string, label: string, percent: float, score: int, total: int, kind: string}>,
     *     by_resource: list<array{title: string, attempts: int, best_percent: float, latest_percent: float, improving: bool}>
     * }
     */
    public function forUser(User $user): array
    {
        $attempts = AssessmentAttempt::query()
            ->with(['learningResource:id,title', 'answers'])
            ->where('user_id', $user->id)
            ->orderBy('completed_at')
            ->get();

        if ($attempts->isEmpty()) {
            return [
                'summary' => [
                    'attempts' => 0,
                    'average_percent' => 0.0,
                    'latest_percent' => null,
                    'trend' => 'none',
                ],
                'strengths' => [],
                'weaknesses' => [],
                'focus' => [
                    'Take a quiz or practice exam to start building your strengths and weaknesses report.',
                ],
                'timeline' => [],
                'by_resource' => [],
            ];
        }

        $percents = $attempts->map(fn (AssessmentAttempt $attempt): float => $attempt->percent());
        $latest = (float) $percents->last();
        $average = round((float) $percents->avg(), 1);
        $trend = $this->trend($percents);

        $topicStats = $this->aggregateByTopic($attempts);
        $strengths = $topicStats
            ->filter(fn (array $row): bool => $row['attempts'] >= 2 && $row['accuracy'] >= 70)
            ->sortByDesc('accuracy')
            ->take(5)
            ->values()
            ->all();

        $weaknesses = $topicStats
            ->filter(fn (array $row): bool => $row['attempts'] >= 2 && $row['accuracy'] < 70)
            ->sortBy('accuracy')
            ->take(5)
            ->map(function (array $row): array {
                $row['focus'] = $row['accuracy'] < 40
                    ? 'Review the basics and retry related practice soon.'
                    : 'Drill this area with another quiz or exam attempt.';

                return $row;
            })
            ->values()
            ->all();

        $missedQuestions = $this->topMissedQuestions($user, 5);

        $focus = [];

        foreach ($weaknesses as $weakness) {
            $focus[] = 'Focus on '.$weakness['label'].' ('.$weakness['accuracy'].'% correct). '.$weakness['focus'];
        }

        foreach ($missedQuestions as $miss) {
            $focus[] = 'Revisit: '.$miss['label'].' (missed '.$miss['misses'].'×).';
        }

        if ($trend === 'improving') {
            array_unshift($focus, 'You are improving over recent attempts — keep a steady practice cadence.');
        } elseif ($trend === 'declining') {
            array_unshift($focus, 'Recent scores dipped — review weak topics before your next attempt.');
        }

        if ($focus === []) {
            $focus[] = 'Keep practicing regularly to sharpen weak spots and track improvement.';
        }

        return [
            'summary' => [
                'attempts' => $attempts->count(),
                'average_percent' => $average,
                'latest_percent' => $latest,
                'trend' => $trend,
            ],
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'focus' => array_values(array_unique($focus)),
            'timeline' => $attempts
                ->sortByDesc('completed_at')
                ->take(12)
                ->values()
                ->map(fn (AssessmentAttempt $attempt): array => [
                    'date' => $attempt->completed_at?->toDateString() ?? '',
                    'label' => $attempt->learningResource?->title ?? 'Assessment',
                    'percent' => $attempt->percent(),
                    'score' => $attempt->score,
                    'total' => $attempt->total,
                    'kind' => $attempt->kind->label(),
                ])
                ->all(),
            'by_resource' => $this->byResource($attempts),
        ];
    }

    /**
     * @param  Collection<int, float>  $percents
     */
    protected function trend(Collection $percents): string
    {
        if ($percents->count() < 2) {
            return 'steady';
        }

        $recent = $percents->slice(-3)->avg();
        $earlier = $percents->slice(0, max(1, $percents->count() - 3))->avg();

        if ($recent === null || $earlier === null) {
            return 'steady';
        }

        $delta = $recent - $earlier;

        if ($delta >= 5) {
            return 'improving';
        }

        if ($delta <= -5) {
            return 'declining';
        }

        return 'steady';
    }

    /**
     * @param  Collection<int, AssessmentAttempt>  $attempts
     * @return Collection<int, array{label: string, accuracy: float, attempts: int}>
     */
    protected function aggregateByTopic(Collection $attempts): Collection
    {
        $buckets = [];

        foreach ($attempts as $attempt) {
            foreach ($attempt->answers as $answer) {
                $labels = [];

                if (is_array($answer->topics) && $answer->topics !== []) {
                    foreach ($answer->topics as $topic) {
                        if (is_string($topic) && filled($topic)) {
                            $labels[] = $topic;
                        }
                    }
                }

                if ($labels === [] && filled($answer->difficulty)) {
                    $labels[] = 'Difficulty: '.ucfirst((string) $answer->difficulty);
                }

                if ($labels === []) {
                    $labels[] = $attempt->learningResource?->title ?? 'General';
                }

                foreach ($labels as $label) {
                    if (! isset($buckets[$label])) {
                        $buckets[$label] = ['correct' => 0, 'total' => 0];
                    }

                    $buckets[$label]['total']++;

                    if ($answer->is_correct) {
                        $buckets[$label]['correct']++;
                    }
                }
            }
        }

        return collect($buckets)
            ->map(fn (array $stats, string $label): array => [
                'label' => $label,
                'accuracy' => $stats['total'] > 0
                    ? round(($stats['correct'] / $stats['total']) * 100, 1)
                    : 0.0,
                'attempts' => $stats['total'],
            ])
            ->values();
    }

    /**
     * @return list<array{label: string, misses: int}>
     */
    protected function topMissedQuestions(User $user, int $limit): array
    {
        return AssessmentAttemptAnswer::query()
            ->selectRaw('question_key, max(question_label) as question_label, sum(case when is_correct = 0 then 1 else 0 end) as misses')
            ->whereHas('attempt', fn ($query) => $query->where('user_id', $user->id))
            ->groupBy('question_key')
            ->havingRaw('sum(case when is_correct = 0 then 1 else 0 end) > 0')
            ->orderByDesc('misses')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->question_label,
                'misses' => (int) $row->misses,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, AssessmentAttempt>  $attempts
     * @return list<array{title: string, attempts: int, best_percent: float, latest_percent: float, improving: bool}>
     */
    protected function byResource(Collection $attempts): array
    {
        return $attempts
            ->groupBy('learning_resource_id')
            ->map(function (Collection $group): array {
                /** @var AssessmentAttempt $latest */
                $latest = $group->sortByDesc('completed_at')->first();
                $percents = $group->map(fn (AssessmentAttempt $attempt): float => $attempt->percent());
                $ordered = $group->sortBy('completed_at')->values();
                $first = $ordered->first()?->percent() ?? 0.0;
                $last = $ordered->last()?->percent() ?? 0.0;

                return [
                    'title' => $latest->learningResource?->title ?? 'Assessment',
                    'attempts' => $group->count(),
                    'best_percent' => round((float) $percents->max(), 1),
                    'latest_percent' => $latest->percent(),
                    'improving' => $group->count() >= 2 && $last > $first,
                ];
            })
            ->sortByDesc('attempts')
            ->values()
            ->take(8)
            ->all();
    }
}
