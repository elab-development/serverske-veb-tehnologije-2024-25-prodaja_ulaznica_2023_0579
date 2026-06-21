<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months');
        $endsAt = (clone $startsAt)->modify('+' . fake()->numberBetween(2, 6) . ' hours');

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraphs(3, true),
            'location' => fake()->city() . ', ' . fake()->streetAddress(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => fake()->randomElement(['draft', 'published', 'cancelled']),
        ];
    }
}