<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'amount' => 30,
            'currency' => 'ETB',
            'provider' => 'manual',
            'external_ref' => 'PAY-'.strtoupper(Str::random(10)),
            'status' => PaymentStatus::Pending,
            'purpose' => 'premium',
            'meta' => null,
        ];
    }
}
