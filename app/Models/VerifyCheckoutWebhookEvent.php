<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_id',
    'event_type',
    'delivery_id',
    'deposit_id',
    'sequence',
    'payload',
    'processed_at',
])]
class VerifyCheckoutWebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }
}
