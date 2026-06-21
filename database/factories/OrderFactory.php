<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 6);

        return [
            'user_id' => User::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => $quantity,
            'total_price' => fake()->randomFloat(2, 10, 1000),
            'status' => fake()->randomElement(['pending', 'paid', 'cancelled']),
            'purchased_at' => fake()->optional()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}