<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_resource_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('daily_challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->unsignedInteger('question_index');
            $table->string('question_key')->nullable();
            $table->string('question_label');
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'qr_status_created_index');
            $table->index(['user_id', 'learning_resource_id', 'question_index', 'status'], 'qr_user_resource_pending_index');
            $table->index(['user_id', 'daily_challenge_id', 'question_index', 'status'], 'qr_user_daily_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_reports');
    }
};
