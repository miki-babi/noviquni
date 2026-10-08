<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->boolean('is_guidance_available')->default(false)->after('guidance_opening_message');
        });

        DB::table('opportunities')
            ->where('is_verified_partner', true)
            ->update(['is_guidance_available' => true]);
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('is_guidance_available');
        });
    }
};
