<?php

namespace App\Models;

use Database\Factories\UserStreakFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'current_streak',
    'longest_streak',
    'last_completed_on',
    'badges',
])]
class UserStreak extends Model
{
    /** @use HasFactory<UserStreakFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_streak' => 0,
        'longest_streak' => 0,
    ];

    /**
     * Badge thresholds unlocked by consecutive days.
     *
     * @var list<int>
     */
    public const BadgeThresholds = [3, 7, 30];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_completed_on' => 'date',
            'badges' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<int>
     */
    public function unlockedBadges(): array
    {
        $badges = $this->badges ?? [];

        return collect($badges)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
