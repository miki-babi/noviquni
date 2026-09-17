<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_resources', function (Blueprint $table) {
            $table->foreignId('module_id')
                ->nullable()
                ->after('semester_id')
                ->constrained('learning_resources')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('module_id');
            $table->boolean('is_bait')->default(false)->after('is_premium');
        });
    }

    public function down(): void
    {
        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
            $table->dropColumn(['sort_order', 'is_bait']);
        });
    }
};
