<?php

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Models\LearningResource;
use App\Models\Subscription;
use App\Models\User;

class PremiumService
{
    public function __construct(public SettingsService $settings) {}

    public function grant(User $user, SubscriptionSource $source, ?int $paymentId = null, ?int $days = null): Subscription
    {
        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'starts_at' => now(),
            'ends_at' => null,
            'source' => $source,
            'payment_id' => $paymentId,
        ]);

        $user->update(['is_premium' => true]);

        return $subscription;
    }

    public function revoke(User $user): void
    {
        $user->update(['is_premium' => false]);
    }

    public function canAccess(User $user, LearningResource $resource): bool
    {
        if (! $resource->is_published) {
            return false;
        }

        if ($user->hasActivePremium()) {
            return true;
        }

        return ! $resource->is_premium;
    }

    public function canRequestOpportunityGuidance(User $user): bool
    {
        return $user->hasActivePremium();
    }
}
