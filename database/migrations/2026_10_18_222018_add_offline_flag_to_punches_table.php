<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->boolean('is_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punches', function (Blueprint $table) {
            $table->dropColumn(['is_offline', 'synced_at']);
        });
    }
};
