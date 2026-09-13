<?php

namespace Database\Factories;

use App\Enums\SubscriptionSource;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'source' => SubscriptionSource::Payment,
            'payment_id' => null,
        ];
    }
}
