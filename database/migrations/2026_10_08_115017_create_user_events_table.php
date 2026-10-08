<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
