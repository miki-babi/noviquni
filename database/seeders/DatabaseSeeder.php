<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Semester;
use App\Services\SettingsService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app(SettingsService::class)->seedDefaults();

        $this->call([
            AdminUserSeeder::class,
            YearSeeder::class,
            OpportunitySeeder::class,
        ]);

        foreach (['Semester 1', 'Semester 2'] as $index => $name) {
            Semester::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1],
            );
        }

        foreach (['Mathematics', 'Physics', 'Chemistry', 'English', 'History', 'Geography', 'Civics'] as $name) {
            Course::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                ],
            );
        }
    }
}
