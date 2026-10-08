<?php

use App\Enums\UserRole;
use App\Enums\YearSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freshmanId = DB::table('years')->where('slug', YearSlug::Freshman->value)->value('id');

        if ($freshmanId === null) {
            return;
        }

        $now = now();

        DB::table('users')
            ->where('role', UserRole::Student->value)
            ->whereNotIn('id', DB::table('user_year')->select('user_id'))
            ->orderBy('id')
            ->chunkById(200, function ($students) use ($freshmanId, $now): void {
                $rows = $students->map(fn ($student): array => [
                    'user_id' => $student->id,
                    'year_id' => $freshmanId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('user_year')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
