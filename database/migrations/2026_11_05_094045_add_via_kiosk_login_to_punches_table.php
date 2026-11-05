<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Punches made on the tablet by typing a phone number and password, for staff without a phone.
     */
    public function up(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->boolean('via_kiosk_login')->default(false)->after('is_manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn('via_kiosk_login');
        });
    }
};
