<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_step', 'department_name']);
        });

        Schema::dropIfExists('user_year');
        Schema::dropIfExists('years');
    }

    public function down(): void
    {
        //
    }
};
