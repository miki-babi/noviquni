<?php

namespace Database\Seeders;

use App\Enums\YearSlug;
use App\Models\Year;
use Illuminate\Database\Seeder;

class YearSeeder extends Seeder
{
    public function run(): void
    {
        foreach (YearSlug::cases() as $slug) {
            Year::query()->updateOrCreate(
                ['slug' => $slug->value],
                [
                    'name' => $slug->label(),
                    'sort_order' => $slug->sortOrder(),
                ],
            );
        }
    }
}
