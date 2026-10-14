<?php

namespace App\Models;

use App\Enums\AssessmentKind;
use Database\Factories\AssessmentAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'learning_resource_id',
    'kind',
    'score',
    'total',
    'timed_out',
    'completed_at',
])]
class AssessmentAttempt extends Model
{
    /** @use HasFactory<AssessmentAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AssessmentKind::class,
            'score' => 'integer',
            'total' => 'integer',
            'timed_out' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function learningResource(): BelongsTo
    {
        return $this->belongsTo(LearningResource::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAttemptAnswer::class);
    }

    public function percent(): float
    {
        if ($this->total <= 0) {
            return 0.0;
        }

        return round(($this->score / $this->total) * 100, 1);
    }
}
