<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_resources', function (Blueprint $table): void {
            $table->string('external_id')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('learning_resources', function (Blueprint $table): void {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
