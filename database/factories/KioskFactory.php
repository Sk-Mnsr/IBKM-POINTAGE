<?php

namespace Database\Factories;

use App\Models\Kiosk;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Kiosk>
 */
class KioskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Accueil',
            'public_id' => (string) Str::ulid(),
            'secret' => Str::random(40),
            'is_active' => true,
        ];
    }
}
