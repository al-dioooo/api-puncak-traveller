<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_sees_all_bookings_while_member_sees_only_their_own(): void
    {
        [$member, $otherMember] = [User::factory()->create(), User::factory()->create()];
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$event, $ticketType] = $this->createEventWithTicket();
        $this->createBooking($member, $event, $ticketType, 'PTR-ONE');
        $this->createBooking($otherMember, $event, $ticketType, 'PTR-TWO');

        Sanctum::actingAs($member);
        $this->getJson(route('api.v1.bookings.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($admin);
        $this->getJson(route('api.v1.bookings.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_refund_is_idempotent_and_restores_ticket_stock_once(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create();
        [$event, $ticketType] = $this->createEventWithTicket(['quantity' => 10, 'sold' => 2]);
        $booking = $this->createBooking($member, $event, $ticketType, 'PTR-REFUND', 2);

        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.bookings.refund', $booking))
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_REFUNDED);

        $this->assertSame(0, $ticketType->refresh()->sold);

        $this->postJson(route('api.v1.bookings.refund', $booking->refresh()))
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_REFUNDED);

        $this->assertSame(0, $ticketType->refresh()->sold);
    }

    /**
     * @param  array<string, mixed>  $ticketAttributes
     * @return array{0: Event, 1: TicketType}
     */
    private function createEventWithTicket(array $ticketAttributes = []): array
    {
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create(array_merge([
            'public_id' => 'general',
            'name' => 'General',
            'quantity' => 10,
            'sold' => 0,
            'price' => 100000,
        ], $ticketAttributes));

        return [$event, $ticketType];
    }

    private function createBooking(User $user, Event $event, TicketType $ticketType, string $reference, int $quantity = 1): Booking
    {
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => $reference,
            'status' => Booking::STATUS_CONFIRMED,
            'subtotal' => $quantity * $ticketType->price,
            'booking_fee' => 5000,
            'total' => ($quantity * $ticketType->price) + 5000,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'unit_price' => $ticketType->price,
        ]);

        return $booking;
    }
}
