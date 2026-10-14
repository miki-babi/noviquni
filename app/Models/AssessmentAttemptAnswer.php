<?php

namespace App\Models;

use Database\Factories\AssessmentAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'assessment_attempt_id',
    'question_index',
    'question_key',
    'question_label',
    'difficulty',
    'topics',
    'selected_index',
    'correct_index',
    'is_correct',
])]
class AssessmentAttemptAnswer extends Model
{
    /** @use HasFactory<AssessmentAttemptAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_index' => 'integer',
            'topics' => 'array',
            'selected_index' => 'integer',
            'correct_index' => 'integer',
            'is_correct' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id');
    }
}
