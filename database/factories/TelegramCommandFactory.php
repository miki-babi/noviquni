<?php

namespace Database\Factories;

use App\Models\LearningResource;
use App\Models\TelegramCommand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TelegramCommand>
 */
class TelegramCommandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'command' => Str::lower(Str::random(8)),
            'message' => fake()->sentence(),
            'learning_resource_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function withResource(?LearningResource $resource = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'learning_resource_id' => $resource?->id ?? LearningResource::factory()->published(),
            'message' => null,
        ]);
    }

    public function messageOnly(string $message = 'Hello from a custom command'): static
    {
        return $this->state(fn (array $attributes): array => [
            'message' => $message,
            'learning_resource_id' => null,
        ]);
    }
}
