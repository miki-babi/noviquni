<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'amount',
    'currency',
    'provider',
    'external_ref',
    'idempotency_key',
    'deposit_id',
    'deposit_status',
    'expires_at',
    'status',
    'purpose',
    'meta',
    'verified_at',
    'verified_by',
    'fulfilled_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const PROVIDER_MANUAL = 'manual';

    public const PROVIDER_VERIFY_CHECKOUT = 'verify_checkout';

    /**
     * Hosted checkout URL for the current request only — never persisted.
     */
    public ?string $checkoutUrl = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'ETB',
        'provider' => self::PROVIDER_MANUAL,
        'status' => 'pending',
        'purpose' => 'premium',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerifyCheckout(): bool
    {
        return $this->provider === self::PROVIDER_VERIFY_CHECKOUT;
    }

    public function isOpenVerifyCheckoutAttempt(): bool
    {
        return $this->isVerifyCheckout()
            && $this->status === PaymentStatus::Pending
            && $this->fulfilled_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'meta' => 'array',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }
}
