<?php

namespace App\Models;

use App\Enums\GuidanceRequestStatus;
use Database\Factories\OpportunityGuidanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'opportunity_id',
    'status',
    'assigned_to_user_id',
    'assigned_at',
])]
class OpportunityGuidanceRequest extends Model
{
    /** @use HasFactory<OpportunityGuidanceRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GuidanceRequestStatus::class,
            'assigned_at' => 'datetime',
        ];
    }
}
