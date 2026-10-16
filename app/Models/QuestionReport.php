<?php

namespace App\Models;

use App\Enums\QuestionReportReason;
use App\Enums\QuestionReportSource;
use App\Enums\QuestionReportStatus;
use Database\Factories\QuestionReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'learning_resource_id',
    'daily_challenge_id',
    'source',
    'question_index',
    'question_key',
    'question_label',
    'reason',
    'note',
    'status',
    'resolved_by',
    'resolved_at',
    'admin_notes',
])]
class QuestionReport extends Model
{
    /** @use HasFactory<QuestionReportFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => QuestionReportSource::class,
            'reason' => QuestionReportReason::class,
            'status' => QuestionReportStatus::class,
            'question_index' => 'integer',
            'resolved_at' => 'datetime',
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

    public function dailyChallenge(): BelongsTo
    {
        return $this->belongsTo(DailyChallenge::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status === QuestionReportStatus::Pending;
    }
}
