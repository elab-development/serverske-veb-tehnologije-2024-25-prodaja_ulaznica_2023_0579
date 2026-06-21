<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ticketTypes = [
            'Belgrade Music Festival' => [
                ['name' => 'Regular', 'price' => 35.00, 'quantity_total' => 800, 'quantity_available' => 620],
                ['name' => 'VIP', 'price' => 90.00, 'quantity_total' => 120, 'quantity_available' => 75],
                ['name' => 'Backstage', 'price' => 150.00, 'quantity_total' => 30, 'quantity_available' => 12],
            ],
            'Laravel Workshop Day' => [
                ['name' => 'Standard Seat', 'price' => 120.00, 'quantity_total' => 80, 'quantity_available' => 42],
                ['name' => 'Student Ticket', 'price' => 60.00, 'quantity_total' => 25, 'quantity_available' => 15],
            ],
            'Stand-up Night Novi Sad' => [
                ['name' => 'General Admission', 'price' => 18.00, 'quantity_total' => 180, 'quantity_available' => 96],
                ['name' => 'Front Row', 'price' => 32.00, 'quantity_total' => 25, 'quantity_available' => 8],
            ],
            'Startup Pitch Evening' => [
                ['name' => 'Visitor Pass', 'price' => 25.00, 'quantity_total' => 150, 'quantity_available' => 150],
                ['name' => 'Investor Pass', 'price' => 75.00, 'quantity_total' => 40, 'quantity_available' => 40],
            ],
        ];

        foreach ($ticketTypes as $eventTitle => $types) {
            $event = Event::query()->where('title', $eventTitle)->first();

            if (! $event) {
                continue;
            }

            foreach ($types as $type) {
                TicketType::query()->updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'name' => $type['name'],
                    ],
                    [
                        'price' => $type['price'],
                        'quantity_total' => $type['quantity_total'],
                        'quantity_available' => $type['quantity_available'],
                        'sale_starts_at' => now()->subWeek(),
                        'sale_ends_at' => $event->starts_at->copy()->subDay(),
                    ]
                );
            }
        }

        Event::query()
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->each(function (Event $event): void {
                TicketType::factory()
                    ->count(2)
                    ->for($event)
                    ->create();
            });
    }
}