<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_commands', function (Blueprint $table) {
            $table->id();
            $table->string('command', 32)->unique();
            $table->text('message')->nullable();
            $table->foreignId('learning_resource_id')
                ->nullable()
                ->constrained('learning_resources')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_commands');
    }
};
