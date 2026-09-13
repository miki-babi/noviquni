<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Semester;
use App\Models\Stream;
use App\Models\University;
use App\Models\User;
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

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@noviquni.test',
            'password' => 'password',
        ]);

        $natural = Stream::query()->create([
            'name' => 'Natural',
            'slug' => 'natural',
            'is_active' => true,
        ]);

        $social = Stream::query()->create([
            'name' => 'Social',
            'slug' => 'social',
            'is_active' => true,
        ]);

        foreach (['Addis Ababa University', 'Bahir Dar University', 'Hawassa University'] as $index => $name) {
            University::query()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }

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
