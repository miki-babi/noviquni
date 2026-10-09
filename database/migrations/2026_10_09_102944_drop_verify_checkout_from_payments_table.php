<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['deposit_id']);
            $table->dropColumn([
                'idempotency_key',
                'deposit_id',
                'deposit_status',
                'expires_at',
            ]);
        });

        Schema::dropIfExists('verify_checkout_webhook_events');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->unique()->after('external_ref');
            $table->string('deposit_id')->nullable()->unique()->after('idempotency_key');
            $table->string('deposit_status')->nullable()->after('deposit_id');
            $table->timestamp('expires_at')->nullable()->after('deposit_status');
        });

        Schema::create('verify_checkout_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('delivery_id')->nullable();
            $table->string('deposit_id')->nullable()->index();
            $table->unsignedBigInteger('sequence')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }
};
