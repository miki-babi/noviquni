<?php

namespace App\Models;

use App\Enums\StudyPlanType;
use Database\Factories\StudyPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id',
    'type',
    'title',
    'week_number',
    'is_published',
    'is_premium',
    'description',
])]
class StudyPlan extends Model
{
    /** @use HasFactory<StudyPlanFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
        'is_premium' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (StudyPlan $plan): void {
            if ($plan->type === StudyPlanType::BaitChecklist) {
                $plan->is_premium = false;
            } else {
                $plan->is_premium = true;
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudyPlanItem::class)->orderBy('sort_order');
    }

    public function canAccess(User $user): bool
    {
        if (! $this->is_published) {
            return false;
        }

        if ($user->hasActivePremium()) {
            return true;
        }

        return $this->type === StudyPlanType::BaitChecklist && ! $this->is_premium;
    }

    /**
     * @param  Builder<StudyPlan>  $query
     * @return Builder<StudyPlan>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StudyPlanType::class,
            'is_published' => 'boolean',
            'is_premium' => 'boolean',
            'week_number' => 'integer',
        ];
    }
}
