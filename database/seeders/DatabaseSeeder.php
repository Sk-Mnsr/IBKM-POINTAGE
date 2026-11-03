<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Kiosk;
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
        foreach (['Coiffure', 'Esthétique', 'Onglerie', 'Caisse'] as $name) {
            Department::query()->firstOrCreate(['name' => $name]);
        }

        $this->createAdmin();

        Kiosk::query()->firstOrCreate(['name' => 'Accueil'], [
            'public_id' => (string) Str::ulid(),
            'secret' => Str::random(40),
        ]);
    }

    private function createAdmin(): void
    {
        $config = config('pointage.admin');
        $password = $config['password'] ?: Str::password(12, symbols: false);

        $admin = User::query()->firstOrCreate(
            ['email' => $config['email'] ?? 'gerant@example.com'],
            [
                'name' => $config['name'],
                'phone' => $config['phone'],
                'password' => $password,
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        if ($admin->wasRecentlyCreated && ! $config['password']) {
            $this->command?->warn("Mot de passe généré pour {$admin->email} : {$password}");
        }
    }
}
