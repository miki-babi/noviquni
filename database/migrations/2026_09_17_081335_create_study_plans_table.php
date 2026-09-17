<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_premium')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plans');
    }
};
