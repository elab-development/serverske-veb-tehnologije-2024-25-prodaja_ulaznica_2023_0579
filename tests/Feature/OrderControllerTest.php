<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated users can create orders and reserve available tickets', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create([
        'price' => 30,
        'quantity_total' => 10,
        'quantity_available' => 5,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'pay_now' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('order.user_id', $user->id)
        ->assertJsonPath('order.ticket_type_id', $ticketType->id)
        ->assertJsonPath('order.quantity', 2)
        ->assertJsonPath('order.total_price', '60.00')
        ->assertJsonPath('order.status', 'paid');

    expect($ticketType->fresh()->quantity_available)->toBe(3);
});

test('orders cannot be created when there are not enough tickets', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $ticketType = TicketType::factory()->create([
        'quantity_total' => 2,
        'quantity_available' => 1,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'pay_now' => false,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect(Order::query()->count())->toBe(0)
        ->and($ticketType->fresh()->quantity_available)->toBe(1);
});

test('users can list their own orders and admins can filter all orders', function () {
    $event = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();
    $otherTicketType = TicketType::factory()->for($otherEvent)->create();
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
        'total_price' => $ticketType->price,
    ]);

    Order::factory()->create([
        'user_id' => $otherUser->id,
        'ticket_type_id' => $otherTicketType->id,
        'quantity' => 1,
        'total_price' => $otherTicketType->price,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('orders.0.user_id', $user->id);

    $this->getJson("/api/orders?user_id={$otherUser->id}")
        ->assertForbidden();

    Sanctum::actingAs($admin);

    $this->getJson("/api/orders?event_id={$event->id}&user_id={$user->id}")
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('filters.event_id', (string) $event->id)
        ->assertJsonPath('filters.user_id', (string) $user->id)
        ->assertJsonPath('orders.0.user_id', $user->id);
});

test('orders can be viewed only by their owner or an admin', function () {
    $owner = User::factory()->create(['role' => User::ROLE_USER]);
    $otherUser = User::factory()->create(['role' => User::ROLE_USER]);
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $ticketType = TicketType::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $owner->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 1,
        'total_price' => $ticketType->price,
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('order.id', $order->id);

    Sanctum::actingAs($otherUser);

    $this->getJson("/api/orders/{$order->id}")
        ->assertForbidden();

    Sanctum::actingAs($admin);

    $this->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('order.id', $order->id);
});

test('pending orders can be paid or cancelled and cancelled orders restore availability', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $ticketType = TicketType::factory()->create([
        'quantity_total' => 10,
        'quantity_available' => 6,
    ]);

    $payOrder = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 2,
        'total_price' => $ticketType->price * 2,
        'status' => 'pending',
        'purchased_at' => null,
    ]);

    $cancelOrder = Order::factory()->create([
        'user_id' => $user->id,
        'ticket_type_id' => $ticketType->id,
        'quantity' => 3,
        'total_price' => $ticketType->price * 3,
        'status' => 'pending',
        'purchased_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/orders/{$payOrder->id}", [
        'status' => 'paid',
    ])
        ->assertOk()
        ->assertJsonPath('order.status', 'paid');

    expect($payOrder->fresh()->purchased_at)->not->toBeNull()
        ->and($ticketType->fresh()->quantity_available)->toBe(6);

    $this->patchJson("/api/orders/{$cancelOrder->id}", [
        'status' => 'cancelled',
    ])
        ->assertOk()
        ->assertJsonPath('order.status', 'cancelled');

    expect($ticketType->fresh()->quantity_available)->toBe(9);

    $this->patchJson("/api/orders/{$payOrder->id}", [
        'status' => 'cancelled',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('only status can be updated on orders', function () {
    $user = User::factory()->create(['role' => User::ROLE_USER]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $this->patchJson("/api/orders/{$order->id}", [
        'status' => 'paid',
        'quantity' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});