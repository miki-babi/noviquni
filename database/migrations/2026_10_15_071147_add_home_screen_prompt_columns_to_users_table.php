<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('home_screen_prompt_count')->default(0)->after('theme');
            $table->timestamp('home_screen_added_at')->nullable()->after('home_screen_prompt_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['home_screen_prompt_count', 'home_screen_added_at']);
        });
    }
};
