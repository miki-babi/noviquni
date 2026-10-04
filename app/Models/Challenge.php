<?php

namespace App\Models;

use App\Enums\ChallengeRewardType;
use App\Enums\ChallengeStatus;
use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'cost_points',
    'reward_type',
    'reward_value',
    'reward_message',
    'status',
    'max_winners',
    'winners_count',
    'created_by',
])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'winners_count' => 0,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChallengeCompletion::class);
    }

    /**
     * Active challenges students can browse.
     *
     * @param  Builder<Challenge>  $query
     * @return Builder<Challenge>
     */
    #[Scope]
    protected function listed(Builder $query): Builder
    {
        return $query
            ->where('status', ChallengeStatus::Active)
            ->where(function (Builder $builder): void {
                $builder->whereNull('max_winners')
                    ->orWhereColumn('winners_count', '<', 'max_winners');
            });
    }

    /**
     * Challenges that can be auto-redeemed now.
     *
     * @param  Builder<Challenge>  $query
     * @return Builder<Challenge>
     */
    #[Scope]
    protected function redeemable(Builder $query): Builder
    {
        return $query->listed();
    }

    public function hasAvailableSlots(): bool
    {
        return $this->max_winners === null || $this->winners_count < $this->max_winners;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_points' => 'integer',
            'reward_value' => 'integer',
            'reward_type' => ChallengeRewardType::class,
            'status' => ChallengeStatus::class,
            'max_winners' => 'integer',
            'winners_count' => 'integer',
        ];
    }
}
