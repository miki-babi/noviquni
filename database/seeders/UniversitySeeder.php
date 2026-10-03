<?php

namespace Database\Seeders;

use App\Models\University;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UniversitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $universities = [
            'Arba Minch University',
            'Arsi University',
            'Assosa University',
            'Bahir Dar University',
            'Bonga University',
            'Debark University',
            'Debre Berhan University',
            'Debre Markos University',
            'Debre Tabor University',
            'Dilla University',
            'Dire Dawa University',
            'Ethiopian Civil Service University',
            'Gambella University',
            'Haramaya University',
            'Hawassa University',
            'Injibara University',
            'Jigjiga University',
            'Jimma University',
            'Kotebe University of Education',
            'Mekelle University',
            'Mizan-Tepi University',
            'Oda Bultum University',
            'Samara University',
            'Selale University',
            'University of Gondar',
            'Wachamo University',
            'Werabe University',
            'Wolaita Sodo University',
            'Woldia University',
            'Wolkite University',
            'Wollega University',
            'Wollo University',
        ];

        foreach ($universities as $index => $name) {
            $slug = Str::slug($name);

            University::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
