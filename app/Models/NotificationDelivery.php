<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'app_notification_id',
    'broadcast_id',
    'user_id',
    'channel',
    'status',
    'body',
    'sent_at',
])]
class NotificationDelivery extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'channel' => 'telegram',
        'status' => 'pending',
    ];

    public function appNotification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
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
            'sent_at' => 'datetime',
        ];
    }
}
