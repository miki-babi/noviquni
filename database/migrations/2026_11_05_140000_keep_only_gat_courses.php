<?php

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const array GAT_COURSES = [
        'maths' => 'Maths',
        'reading-writing' => 'Reading/writing',
        'analytical-reasoning' => 'Analytical reasoning',
    ];

    public function up(): void
    {
        foreach (self::GAT_COURSES as $slug => $name) {
            Course::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true],
            );
        }

        Course::query()->whereNotIn('slug', array_keys(self::GAT_COURSES))->delete();
    }

    public function down(): void
    {
        //
    }
};
