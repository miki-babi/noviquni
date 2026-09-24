<?php

namespace Database\Factories;

use App\Models\TelegramFileAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TelegramFileAsset>
 */
class TelegramFileAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true).'.pdf';

        return [
            'file_id' => 'BQACAgQAAxkBAAI'.Str::random(24),
            'file_unique_id' => 'AgAD'.Str::random(16),
            'file_name' => $name,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'uploaded_by_username' => 'vault_admin',
        ];
    }
}
