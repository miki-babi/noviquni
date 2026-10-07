<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('guidance_contact_username')->nullable()->after('partner_name');
            $table->text('guidance_opening_message')->nullable()->after('guidance_contact_username');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn(['guidance_contact_username', 'guidance_opening_message']);
        });
    }
};
