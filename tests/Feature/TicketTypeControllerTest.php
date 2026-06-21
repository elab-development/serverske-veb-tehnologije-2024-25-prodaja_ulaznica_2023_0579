<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('event ticket types can be listed without pagination', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();

    $regular = TicketType::factory()->for($event)->create([
        'name' => 'Regular',
        'price' => 35,
        'quantity_total' => 100,
        'quantity_available' => 80,
    ]);

    TicketType::factory()->for($event)->create([
        'name' => 'VIP',
        'price' => 90,
        'quantity_total' => 20,
        'quantity_available' => 12,
    ]);

    TicketType::factory()->for($otherEvent)->create([
        'name' => 'Other Event Ticket',
    ]);

    $response = $this->getJson("/api/events/{$event->id}/ticket-types");

    $response
        ->assertOk()
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonCount(2, 'ticket_types')
        ->assertJsonMissingPath('total')
        ->assertJsonMissingPath('per_page')
        ->assertJsonPath('ticket_types.0.event.id', $event->id)
        ->assertJsonFragment(['id' => $regular->id])
        ->assertJsonMissing(['name' => 'Other Event Ticket']);
});

test('event ticket type show is scoped to the event', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->getJson("/api/events/{$event->id}/ticket-types/{$ticketType->id}")
        ->assertOk()
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonPath('ticket_type.id', $ticketType->id);

    $this->getJson("/api/events/{$otherEvent->id}/ticket-types/{$ticketType->id}")
        ->assertNotFound();
});

test('only admins can manage ticket types', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $payload = [
        'event_id' => $event->id,
        'name' => 'Early Bird',
        'price' => 25,
        'quantity_total' => 150,
        'quantity_available' => 150,
        'sale_starts_at' => now()->toISOString(),
        'sale_ends_at' => now()->addWeek()->toISOString(),
    ];

    Sanctum::actingAs($user);

    $this->postJson('/api/ticket-types', $payload)
        ->assertForbidden();

    Sanctum::actingAs($admin);

    $ticketTypeId = $this->postJson('/api/ticket-types', $payload)
        ->assertCreated()
        ->assertJsonPath('ticket_type.name', 'Early Bird')
        ->json('ticket_type.id');

    $this->patchJson("/api/ticket-types/{$ticketTypeId}", [
        'quantity_available' => 120,
    ])
        ->assertOk()
        ->assertJsonPath('ticket_type.quantity_available', 120);

    $this->deleteJson("/api/ticket-types/{$ticketTypeId}")
        ->assertOk()
        ->assertJsonPath('message', 'Ticket type deleted successfully.');

    expect(TicketType::query()->find($ticketTypeId))->toBeNull();
});