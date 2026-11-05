<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_year', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique(['user_id', 'year_id']);
        });

        $freshmanId = DB::table('years')->where('slug', 'freshman')->value('id');

        if ($freshmanId === null) {
            return;
        }

        $now = now();

        DB::table('users')
            ->where('role', UserRole::Student->value)
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
        Schema::dropIfExists('user_year');
    }
};
