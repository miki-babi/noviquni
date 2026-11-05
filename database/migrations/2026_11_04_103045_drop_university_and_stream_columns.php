<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropForeign(['stream_id']);
            $table->dropIndex(['university_id']);
            $table->dropIndex(['stream_id']);
            $table->dropColumn(['university_id', 'stream_id']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_id');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stream_id');
            $table->dropConstrainedForeignId('university_id');
        });

        Schema::dropIfExists('universities');
        Schema::dropIfExists('streams');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: removed tables and columns are not recreated.
    }
};
