<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['shop_id', 'name']);
        });

        Schema::table('kiosks', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $this->moveExistingDataIntoAFirstShop();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kiosks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'name']);
            $table->dropConstrainedForeignId('shop_id');
            $table->unique('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);

        Schema::dropIfExists('shop_user');
        Schema::dropIfExists('shops');
    }

    /**
     * An installation created before multi-shop support keeps working: its data goes into one shop,
     * and its manager becomes super admin.
     */
    private function moveExistingDataIntoAFirstShop(): void
    {
        $hasData = DB::table('users')->exists() || DB::table('departments')->exists() || DB::table('kiosks')->exists();

        if (! $hasData) {
            return;
        }

        $shopId = DB::table('shops')->insertGetId([
            'name' => config('pointage.institute_name'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('role', 'staff')->update(['shop_id' => $shopId]);
        DB::table('departments')->update(['shop_id' => $shopId]);
        DB::table('kiosks')->update(['shop_id' => $shopId]);
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }
};
