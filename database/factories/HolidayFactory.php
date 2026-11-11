<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeThisYear()->format('Y-m-d'),
            'name' => fake()->randomElement(['Tabaski', 'Korité', 'Tamkharit', 'Maouloud', 'Grand Magal de Touba']),
        ];
    }
}
