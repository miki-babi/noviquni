<?php

namespace Database\Factories;

use App\Enums\GuidanceRequestStatus;
use App\Models\Opportunity;
use App\Models\OpportunityGuidanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityGuidanceRequest>
 */
class OpportunityGuidanceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'opportunity_id' => Opportunity::factory()->published()->verifiedPartner(),
            'status' => GuidanceRequestStatus::Pending,
            'assigned_to_user_id' => null,
            'assigned_at' => null,
        ];
    }

    public function assigned(?User $assignee = null): static
    {
        return $this->state(function () use ($assignee): array {
            $user = $assignee ?? User::factory()->student()->create([
                'telegram_username' => fake()->unique()->userName(),
            ]);

            return [
                'status' => GuidanceRequestStatus::Assigned,
                'assigned_to_user_id' => $user->id,
                'assigned_at' => now(),
            ];
        });
    }
}
