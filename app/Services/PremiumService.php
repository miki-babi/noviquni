<?php

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Models\LearningResource;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

class PremiumService
{
    public function __construct(public SettingsService $settings) {}

    public function grant(User $user, SubscriptionSource $source, ?int $paymentId = null, ?int $days = null): Subscription
    {
        $days ??= $this->settings->premiumDurationDays();
        $startsAt = now();
        $endsAt = ($user->hasActivePremium() ? Carbon::parse($user->premium_until) : $startsAt)->copy()->addDays($days);

        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source' => $source,
            'payment_id' => $paymentId,
        ]);

        $user->update(['premium_until' => $endsAt]);

        return $subscription;
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
}
