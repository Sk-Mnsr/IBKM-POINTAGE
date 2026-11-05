<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'King Maelick '.fake()->unique()->city(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->numerify('33#######'),
            'is_active' => true,
        ];
    }

    /**
     * Records created without an explicit shop join the first one, so single-shop scenarios need no setup.
     */
    public static function firstOrNew(): int
    {
        return Shop::query()->orderBy('id')->value('id') ?? Shop::factory()->create()->id;
    }
}
