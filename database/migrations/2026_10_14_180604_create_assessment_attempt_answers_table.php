<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_attempt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('question_index');
            $table->string('question_key', 64);
            $table->string('question_label', 255);
            $table->string('difficulty', 32)->nullable();
            $table->json('topics')->nullable();
            $table->unsignedSmallInteger('selected_index')->nullable();
            $table->unsignedSmallInteger('correct_index');
            $table->boolean('is_correct');
            $table->timestamps();

            $table->index(['question_key', 'is_correct']);
            $table->index(['assessment_attempt_id', 'question_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attempt_answers');
    }
};
