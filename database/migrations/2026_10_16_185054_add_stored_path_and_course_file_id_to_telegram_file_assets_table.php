<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_file_assets', function (Blueprint $table) {
            $table->string('stored_path')->nullable()->after('uploaded_by_username');
            $table->foreignId('course_file_id')
                ->nullable()
                ->after('stored_path')
                ->constrained('course_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_file_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_file_id');
            $table->dropColumn('stored_path');
        });
    }
};
