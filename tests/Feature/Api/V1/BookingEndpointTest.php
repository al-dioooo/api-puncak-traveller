<?php

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BookingEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_member_can_create_booking_for_event_tickets(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create(['slug' => 'booking-run']);
        $ticketType = TicketType::factory()->for($event)->create([
            'price' => 50000,
            'quantity' => 10,
            'sold' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('Idempotency-Key', 'booking-run-001')
            ->postJson(route('api.v1.events.bookings.store', $event), [
                'items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.total', 100000)
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->assertSame(2, $ticketType->refresh()->sold);
    }

    public function test_booking_requires_authentication(): void
    {
        $event = Event::factory()->upcoming()->create(['slug' => 'private-booking-run']);
        $ticketType = TicketType::factory()->for($event)->create();

        $response = $this
            ->withHeader('Idempotency-Key', 'booking-run-002')
            ->postJson(route('api.v1.events.bookings.store', $event), [
                'items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
                ],
            ]);

        $response->assertUnauthorized();
    }

    public function test_booking_requires_idempotency_key(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('api.v1.events.bookings.store', $event), [
                'items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
                ],
            ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_member_can_list_their_bookings(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create();

        $this
            ->actingAs($user)
            ->withHeader('Idempotency-Key', 'booking-run-003')
            ->postJson(route('api.v1.events.bookings.store', $event), [
                'items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();

        $this
            ->actingAs($user)
            ->getJson(route('api.v1.bookings.index'))
            ->assertOk()
            ->assertJsonPath('data.0.event.id', $event->id);
    }
}
