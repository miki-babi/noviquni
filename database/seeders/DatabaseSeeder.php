<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Semester;
use App\Models\Stream;
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
            UniversitySeeder::class,
            YearSeeder::class,
            OpportunitySeeder::class,
        ]);

        $natural = Stream::query()->firstOrCreate(
            ['slug' => 'natural'],
            ['name' => 'Natural', 'is_active' => true],
        );

        $social = Stream::query()->firstOrCreate(
            ['slug' => 'social'],
            ['name' => 'Social', 'is_active' => true],
        );

        foreach (['Semester 1', 'Semester 2'] as $index => $name) {
            Semester::query()->create([
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
        }

        foreach (['Mathematics', 'Physics', 'Chemistry', 'English'] as $name) {
            Course::query()->create([
                'stream_id' => $natural->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
            ]);
        }

        foreach (['History', 'Geography', 'Civics', 'English'] as $name) {
            Course::query()->create([
                'stream_id' => $social->id,
                'name' => $name,
                'slug' => Str::slug($name).($name === 'English' ? '-social' : ''),
                'is_active' => true,
            ]);
        }
    }
}
