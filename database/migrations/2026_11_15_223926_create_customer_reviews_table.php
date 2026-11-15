<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer satisfaction ratings left on a shop kiosk tablet.
     */
    public function up(): void
    {
        Schema::create('customer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kiosk_id')->constrained()->cascadeOnDelete();
            $table->string('rating');
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['kiosk_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_reviews');
    }
};
