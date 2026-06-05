<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateBooking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    public function test_booking_creation_is_idempotent_for_retried_requests(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'price' => 25000,
            'quantity' => 5,
            'sold' => 0,
        ]);
        $action = app(CreateBooking::class);

        $firstBooking = $action->execute($user, $event, [
            ['ticket_tier_id' => $ticketType->public_id, 'quantity' => 2],
        ], [], 'retry-key-001');
        $secondBooking = $action->execute($user, $event, [
            ['ticket_tier_id' => $ticketType->public_id, 'quantity' => 2],
        ], [], 'retry-key-001');

        $this->assertTrue($firstBooking->is($secondBooking));
        $this->assertSame(2, $ticketType->refresh()->sold);
    }

    public function test_booking_creation_rejects_overselling(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'quantity' => 2,
            'sold' => 1,
        ]);
        $action = app(CreateBooking::class);

        $this->expectExceptionMessage('Only 1');

        $action->execute($user, $event, [
            ['ticket_tier_id' => $ticketType->public_id, 'quantity' => 2],
        ], [], 'oversell-key-001');
    }

    public function test_booking_creation_accepts_resource_model_id_when_ticket_public_id_is_missing(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $ticketType = TicketType::factory()->for($event)->create([
            'public_id' => null,
            'price' => 100000,
            'quantity' => 5,
            'sold' => 0,
        ]);
        $action = app(CreateBooking::class);

        $booking = $action->execute($user, $event, [
            ['ticket_tier_id' => (string) $ticketType->id, 'quantity' => 2],
        ], [], 'missing-public-id-key-001');

        $this->assertSame(2, $ticketType->refresh()->sold);
        $this->assertSame(200000, $booking->subtotal);
        $this->assertCount(1, $booking->items);
        $this->assertSame((string) $ticketType->id, (string) $booking->items->first()->ticket_type_id);
    }

    public function test_booking_creation_rejects_ticket_identifier_from_another_event(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->upcoming()->create();
        $otherEvent = Event::factory()->upcoming()->create();
        $otherTicketType = TicketType::factory()->for($otherEvent)->create([
            'public_id' => null,
            'quantity' => 5,
            'sold' => 0,
        ]);
        $action = app(CreateBooking::class);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('One or more ticket tiers are not available for this event.');

        $action->execute($user, $event, [
            ['ticket_tier_id' => (string) $otherTicketType->id, 'quantity' => 1],
        ], [], 'cross-event-key-001');
    }
}
