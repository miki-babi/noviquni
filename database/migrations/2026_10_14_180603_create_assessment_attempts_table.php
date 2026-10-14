<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_resource_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->boolean('timed_out')->default(false);
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['user_id', 'completed_at']);
            $table->index(['user_id', 'learning_resource_id', 'completed_at']);
            $table->index(['learning_resource_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempts');
    }
};
