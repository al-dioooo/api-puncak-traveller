<?php

namespace Tests\Feature\Actions;

use App\Actions\CreateBooking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ], 'retry-key-001');
        $secondBooking = $action->execute($user, $event, [
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ], 'retry-key-001');

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
            ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
        ], 'oversell-key-001');
    }
}
