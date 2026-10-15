<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme', 16)->default('light')->change();
        });

        DB::table('users')->where('theme', 'dark')->update(['theme' => 'light']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('theme', 16)->default('dark')->change();
        });
    }
};
