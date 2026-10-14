<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['stream_id']);
            $table->dropUnique(['stream_id', 'slug']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable()->change();
            $table->foreign('stream_id')->references('id')->on('streams')->nullOnDelete();
            $table->unique('slug');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropForeign(['stream_id']);
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable()->change();
            $table->foreign('stream_id')->references('id')->on('streams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['stream_id']);
            $table->dropUnique(['slug']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable(false)->change();
            $table->foreign('stream_id')->references('id')->on('streams')->cascadeOnDelete();
            $table->unique(['stream_id', 'slug']);
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropForeign(['stream_id']);
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->foreignId('stream_id')->nullable(false)->change();
            $table->foreign('stream_id')->references('id')->on('streams')->cascadeOnDelete();
        });
    }
};
