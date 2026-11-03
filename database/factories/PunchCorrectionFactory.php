<?php

namespace Database\Factories;

use App\Models\Punch;
use App\Models\PunchCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PunchCorrection>
 */
class PunchCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'punch_id' => Punch::factory(),
            'user_id' => fn (array $attributes) => Punch::query()->find($attributes['punch_id'])?->user_id,
            'author_id' => null,
            'punch_date' => today()->toDateString(),
            'action' => PunchCorrection::ACTION_ADDED,
            'before' => null,
            'after' => ['type' => 'arrival', 'label' => 'Arrivée', 'time' => '09:00'],
            'reason' => fake()->sentence(),
        ];
    }
}
