<?php

namespace Database\Factories;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Punch>
 */
class PunchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kiosk_id' => Kiosk::factory(),
            'type' => PunchType::Arrival,
            'punched_at' => now(),
            'qr_window' => 1,
        ];
    }
}
