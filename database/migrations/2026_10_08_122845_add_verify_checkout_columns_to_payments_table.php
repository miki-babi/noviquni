<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->unique()->after('external_ref');
            $table->string('deposit_id')->nullable()->unique()->after('idempotency_key');
            $table->string('deposit_status')->nullable()->after('deposit_id');
            $table->timestamp('expires_at')->nullable()->after('deposit_status');
            $table->timestamp('fulfilled_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['deposit_id']);
            $table->dropColumn([
                'idempotency_key',
                'deposit_id',
                'deposit_status',
                'expires_at',
                'fulfilled_at',
            ]);
        });
    }
};
