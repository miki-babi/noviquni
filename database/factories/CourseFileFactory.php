<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseFile;
use App\Support\LearningResourceFiles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseFile>
 */
class CourseFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'path' => fn (array $attributes): string => LearningResourceFiles::directoryForCourse($attributes['course_id']).'/'.Str::uuid()->toString().'.pdf',
            'disk' => LearningResourceFiles::diskName(),
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'mime' => 'application/pdf',
            'is_published' => false,
            'external_id' => null,
            'telegram_file_id' => null,
        ];
    }
}
