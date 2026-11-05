<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('years')->insert([
            ['name' => 'Freshman', 'slug' => 'freshman', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '2nd year', 'slug' => '2nd', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '3rd year', 'slug' => '3rd', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '4th year and above', 'slug' => '4th_and_above', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Graduate', 'slug' => 'graduate', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('years');
    }
};
