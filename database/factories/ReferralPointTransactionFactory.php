<?php

namespace Database\Factories;

use App\Enums\ReferralPointTransactionType;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralPointTransaction>
 */
class ReferralPointTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'amount' => 1,
            'type' => ReferralPointTransactionType::ReferralEarned,
            'referral_id' => null,
            'challenge_id' => null,
            'meta' => null,
        ];
    }
}
