<?php

namespace App\Models;

use Database\Factories\DailyChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'challenge_date',
    'questions',
])]
class DailyChallenge extends Model
{
    /** @use HasFactory<DailyChallengeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challenge_date' => 'date',
            'questions' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(DailyChallengeAttempt::class);
    }

    /**
     * Player payload compatible with the study quiz component.
     *
     * @return array{questions: list<array<string, mixed>>}
     */
    public function playerPayload(): array
    {
        $questions = collect($this->questions ?? [])
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = is_array($item['question'] ?? null)
                    ? $item['question']
                    : $item;

                if (! is_array($question) || ! array_key_exists('options', $question)) {
                    return null;
                }

                return $question;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'questions' => $questions,
        ];
    }
}
