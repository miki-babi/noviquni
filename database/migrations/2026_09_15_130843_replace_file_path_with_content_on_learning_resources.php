<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropColumn('file_path');
            $table->json('content')->nullable()->after('is_published');
            $table->string('generation_kind')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropColumn(['content', 'generation_kind']);
            $table->string('file_path')->nullable()->after('is_published');
        });
    }
};
