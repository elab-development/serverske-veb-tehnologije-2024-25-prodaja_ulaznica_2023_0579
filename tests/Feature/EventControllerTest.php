<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('events can be listed with search filters sorting and pagination', function () {
    Event::factory()->create([
        'title' => 'Belgrade Music Festival',
        'description' => 'Open-air music event.',
        'location' => 'Kalemegdan Fortress, Belgrade',
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addHours(3),
        'status' => 'published',
    ]);

    Event::factory()->create([
        'title' => 'Laravel Workshop Day',
        'description' => 'Backend workshop.',
        'location' => 'ICT Hub, Belgrade',
        'starts_at' => now()->addWeeks(2),
        'ends_at' => now()->addWeeks(2)->addHours(6),
        'status' => 'draft',
    ]);

    $response = $this->getJson('/api/events?search=Music&status=published&location=Belgrade&sort_by=title&sort_direction=asc&per_page=5');

    $response
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('per_page', 5)
        ->assertJsonPath('sort.by', 'title')
        ->assertJsonPath('sort.direction', 'asc')
        ->assertJsonPath('events.0.title', 'Belgrade Music Festival');
});

test('only admins can manage events', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $payload = [
        'title' => 'Admin Created Event',
        'description' => 'Created through the admin API.',
        'location' => 'Sava Center, Belgrade',
        'starts_at' => now()->addMonth()->toISOString(),
        'ends_at' => now()->addMonth()->addHours(2)->toISOString(),
        'status' => 'published',
    ];

    Sanctum::actingAs($user);

    $this->postJson('/api/events', $payload)
        ->assertForbidden();

    Sanctum::actingAs($admin);

    $eventId = $this->postJson('/api/events', $payload)
        ->assertCreated()
        ->assertJsonPath('event.title', 'Admin Created Event')
        ->json('event.id');

    $this->patchJson("/api/events/{$eventId}", [
        'status' => 'cancelled',
    ])
        ->assertOk()
        ->assertJsonPath('event.status', 'cancelled');

    $this->deleteJson("/api/events/{$eventId}")
        ->assertOk()
        ->assertJsonPath('message', 'Event deleted successfully.');

    expect(Event::query()->find($eventId))->toBeNull();
});
test('events can be exported to csv', function () {
    $event = Event::factory()->create([
        'title' => 'CSV Export Event',
        'description' => 'Event included in CSV export.',
        'location' => 'Belgrade Arena',
        'starts_at' => '2026-08-01 20:00:00',
        'ends_at' => '2026-08-01 23:00:00',
        'status' => 'published',
    ]);

    TicketType::factory()->for($event)->create([
        'quantity_total' => 100,
        'quantity_available' => 80,
    ]);

    TicketType::factory()->for($event)->create([
        'quantity_total' => 50,
        'quantity_available' => 20,
    ]);

    $response = $this->get('/api/events/export');

    $response
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toContain('id,title,description,location,starts_at,ends_at,status,ticket_types_count,tickets_total,tickets_available,created_at,updated_at')
        ->and($csv)->toContain('CSV Export Event')
        ->and($csv)->toContain('published')
        ->and($csv)->toContain(',2,150,100,');
});