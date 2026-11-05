<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! Shop::query()->exists()) {
            Shop::query()->create(['name' => config('pointage.institute_name')])->provisionDefaults();
        }

        $this->createSuperAdmin();
    }

    private function createSuperAdmin(): void
    {
        $config = config('pointage.admin');
        $password = $config['password'] ?: Str::password(12, symbols: false);

        $admin = User::query()->firstOrCreate(
            ['email' => $config['email'] ?? 'gerant@example.com'],
            [
                'name' => $config['name'],
                'phone' => $config['phone'],
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );

        if ($admin->wasRecentlyCreated && ! $config['password']) {
            $this->command?->warn("Mot de passe généré pour {$admin->email} : {$password}");
        }
    }
}
