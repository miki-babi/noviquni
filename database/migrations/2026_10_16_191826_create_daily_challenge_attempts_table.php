<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_challenge_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_challenge_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'daily_challenge_id'], 'dca_user_challenge_unique');
            $table->index(['daily_challenge_id', 'score', 'duration_ms'], 'dca_leaderboard_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_challenge_attempts');
    }
};
