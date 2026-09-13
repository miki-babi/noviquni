<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('university_id')->references('id')->on('universities')->nullOnDelete();
            $table->foreign('stream_id')->references('id')->on('streams')->nullOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->nullOnDelete();
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropForeign(['broadcast_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropForeign(['stream_id']);
            $table->dropForeign(['semester_id']);
        });
    }
};
