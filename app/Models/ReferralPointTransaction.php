<?php

namespace App\Models;

use App\Enums\ReferralPointTransactionType;
use Database\Factories\ReferralPointTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'amount',
    'type',
    'referral_id',
    'challenge_id',
    'meta',
])]
class ReferralPointTransaction extends Model
{
    /** @use HasFactory<ReferralPointTransactionFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => ReferralPointTransactionType::class,
            'meta' => 'array',
        ];
    }
}
