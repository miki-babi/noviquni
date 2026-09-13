<?php

namespace Database\Factories;

use App\Enums\RewardStatus;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralReward>
 */
class ReferralRewardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $referral = Referral::factory()->create();

        return [
            'referral_id' => $referral->id,
            'user_id' => $referral->referrer_id,
            'amount' => fake()->randomFloat(2, 2, 10),
            'status' => RewardStatus::Pending,
        ];
    }
}
