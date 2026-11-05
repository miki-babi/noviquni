<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Services\SettingsService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(SettingsService::class)->seedDefaults();

        $this->call([
            AdminUserSeeder::class,
            OpportunitySeeder::class,
        ]);

        foreach ([
            'maths' => 'Maths',
            'reading-writing' => 'Reading/writing',
            'analytical-reasoning' => 'Analytical reasoning',
        ] as $slug => $name) {
            Course::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                ],
            );
        }
    }
}
