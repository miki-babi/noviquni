<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('path');
            $table->string('disk')->default('local');
            $table->unsignedBigInteger('size');
            $table->string('mime')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('external_id')->nullable()->unique();
            $table->string('telegram_file_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_files');
    }
};
