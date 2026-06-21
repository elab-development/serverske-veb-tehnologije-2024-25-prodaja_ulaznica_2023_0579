<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(50, 500);
        $saleStartsAt = fake()->dateTimeBetween('-1 week', '+1 month');
        $saleEndsAt = fake()->dateTimeBetween($saleStartsAt, '+5 months');

        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['Regular', 'VIP', 'Early Bird', 'Balcony', 'Front Row']),
            'price' => fake()->randomFloat(2, 10, 250),
            'quantity_total' => $quantity,
            'quantity_available' => fake()->numberBetween(0, $quantity),
            'sale_starts_at' => $saleStartsAt,
            'sale_ends_at' => $saleEndsAt,
        ];
    }
}