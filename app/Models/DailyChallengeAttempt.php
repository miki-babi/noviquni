<?php

namespace App\Models;

use Database\Factories\DailyChallengeAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'daily_challenge_id',
    'score',
    'total',
    'duration_ms',
    'completed_at',
])]
class DailyChallengeAttempt extends Model
{
    /** @use HasFactory<DailyChallengeAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total' => 'integer',
            'duration_ms' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyChallenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class);
    }

    public function percent(): float
    {
        if ($this->total <= 0) {
            return 0.0;
        }

        return round(($this->score / $this->total) * 100, 1);
    }
}
