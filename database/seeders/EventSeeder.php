<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'title' => 'Belgrade Music Festival',
                'description' => 'Open-air evening with regional pop and rock performers.',
                'location' => 'Kalemegdan Fortress, Belgrade',
                'starts_at' => now()->addMonths(2)->setTime(20, 0),
                'ends_at' => now()->addMonths(2)->setTime(23, 30),
                'status' => 'published',
            ],
            [
                'title' => 'Laravel Workshop Day',
                'description' => 'Full-day workshop for building APIs and admin panels with Laravel.',
                'location' => 'ICT Hub, Belgrade',
                'starts_at' => now()->addWeeks(5)->setTime(10, 0),
                'ends_at' => now()->addWeeks(5)->setTime(17, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Stand-up Night Novi Sad',
                'description' => 'Comedy night with three local stand-up performers.',
                'location' => 'Cultural Center, Novi Sad',
                'starts_at' => now()->addWeeks(8)->setTime(21, 0),
                'ends_at' => now()->addWeeks(8)->setTime(23, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Startup Pitch Evening',
                'description' => 'Founders pitch new products to investors and the local tech community.',
                'location' => 'Science Technology Park, Nis',
                'starts_at' => now()->addMonths(3)->setTime(18, 0),
                'ends_at' => now()->addMonths(3)->setTime(21, 0),
                'status' => 'draft',
            ],
        ];

        foreach ($events as $event) {
            Event::query()->updateOrCreate(
                ['title' => $event['title']],
                $event
            );
        }

        Event::factory()
            ->count(8)
            ->create();
    }
}