<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_resource_id')
                ->nullable()
                ->constrained('learning_resources')
                ->nullOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['study_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_plan_items');
    }
};
