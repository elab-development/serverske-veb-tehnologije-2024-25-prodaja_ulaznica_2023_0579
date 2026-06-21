<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orders = [
            ['email' => 'marko.petrovic@example.com', 'event' => 'Belgrade Music Festival', 'ticket' => 'Regular', 'quantity' => 2, 'status' => 'paid'],
            ['email' => 'jovana.ilic@example.com', 'event' => 'Laravel Workshop Day', 'ticket' => 'Student Ticket', 'quantity' => 1, 'status' => 'paid'],
            ['email' => 'nikola.savic@example.com', 'event' => 'Stand-up Night Novi Sad', 'ticket' => 'Front Row', 'quantity' => 2, 'status' => 'pending'],
            ['email' => 'marko.petrovic@example.com', 'event' => 'Startup Pitch Evening', 'ticket' => 'Visitor Pass', 'quantity' => 3, 'status' => 'cancelled'],
        ];

        foreach ($orders as $orderData) {
            $user = User::query()->where('email', $orderData['email'])->first();
            $ticketType = TicketType::query()
                ->where('name', $orderData['ticket'])
                ->whereHas('event', function ($query) use ($orderData): void {
                    $query->where('title', $orderData['event']);
                })
                ->first();

            if (! $user || ! $ticketType) {
                continue;
            }

            $quantity = $orderData['quantity'];

            Order::query()->create([
                'user_id' => $user->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
                'total_price' => $ticketType->price * $quantity,
                'status' => $orderData['status'],
                'purchased_at' => $orderData['status'] === 'paid' ? now()->subDays(fake()->numberBetween(1, 14)) : null,
            ]);
        }

        $users = User::query()->where('role', 'user')->get();
        $ticketTypes = TicketType::query()->get();

        foreach (range(1, 15) as $index) {
            $ticketType = $ticketTypes->random();
            $quantity = fake()->numberBetween(1, 4);

            Order::factory()->create([
                'user_id' => $users->random()->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $quantity,
                'total_price' => $ticketType->price * $quantity,
            ]);
        }
    }
}