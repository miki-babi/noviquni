<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['semester_id']);
            $table->dropIndex(['semester_id']);
            $table->dropColumn('semester_id');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropForeign(['semester_id']);
            $table->dropColumn('semester_id');
        });

        Schema::dropIfExists('semesters');
    }

    public function down(): void
    {
        //
    }
};
