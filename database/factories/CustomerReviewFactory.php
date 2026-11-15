<?php

namespace Database\Factories;

use App\Enums\CustomerReviewRating;
use App\Models\CustomerReview;
use App\Models\Kiosk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReview>
 */
class CustomerReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kiosk_id' => Kiosk::factory(),
            'shop_id' => function (array $attributes): int {
                return (int) Kiosk::query()->findOrFail($attributes['kiosk_id'])->shop_id;
            },
            'rating' => fake()->randomElement(CustomerReviewRating::cases()),
            'comment' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function verySatisfied(): static
    {
        return $this->state(fn (): array => ['rating' => CustomerReviewRating::VerySatisfied]);
    }

    public function unsatisfied(): static
    {
        return $this->state(fn (): array => ['rating' => CustomerReviewRating::Unsatisfied]);
    }
}
