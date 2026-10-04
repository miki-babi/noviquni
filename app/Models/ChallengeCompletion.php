<?php

namespace App\Models;

use Database\Factories\ChallengeCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'challenge_id',
    'user_id',
    'points_spent',
    'completed_at',
])]
class ChallengeCompletion extends Model
{
    /** @use HasFactory<ChallengeCompletionFactory> */
    use HasFactory;

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
