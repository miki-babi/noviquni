<?php

namespace Database\Factories;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'body' => 'Hi {{first_name}}, new resources are available for {{stream}} students.',
            'targeting' => ['audience' => 'everyone'],
            'status' => BroadcastStatus::Draft,
            'created_by' => User::factory()->admin(),
        ];
    }
}
