<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_command_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_command_id')
                ->constrained('telegram_commands')
                ->cascadeOnDelete();
            $table->foreignId('telegram_file_asset_id')
                ->constrained('telegram_file_assets')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unique(
                ['telegram_command_id', 'telegram_file_asset_id'],
                'telegram_command_file_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_command_file');
    }
};
