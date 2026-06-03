<?php

namespace Tests\Feature\Api\V1;

use App\Mail\BookingReceiptMail;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_sees_all_bookings_while_member_sees_only_their_own(): void
    {
        [$member, $otherMember] = [User::factory()->create(), User::factory()->create()];
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$event, $ticketType] = $this->createEventWithTicket([], [
            'cover_image' => '/events/puncak-trail-run-2026.jpg',
            'image_alt' => 'Runners crossing a highland trail',
        ]);
        $this->createBooking($member, $event, $ticketType, 'PTR-ONE');
        $this->createBooking($otherMember, $event, $ticketType, 'PTR-TWO');

        Sanctum::actingAs($member);
        $this->getJson(route('api.v1.bookings.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($admin);
        $this->getJson(route('api.v1.bookings.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event.imageUrl', '/events/puncak-trail-run-2026.jpg')
            ->assertJsonPath('data.0.event.imageAlt', 'Runners crossing a highland trail');
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

    public function test_admin_resends_booking_receipt_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['email' => 'alex@example.com']);
        [$event, $ticketType] = $this->createEventWithTicket();
        $booking = $this->createBooking($member, $event, $ticketType, 'PTR-RECEIPT');

        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.bookings.resend-receipt', $booking))
            ->assertOk()
            ->assertJsonPath('data.reference', 'PTR-RECEIPT')
            ->assertJsonStructure(['data' => ['resentAt']]);

        Mail::assertSent(BookingReceiptMail::class, fn (BookingReceiptMail $mail): bool => $mail->hasTo('alex@example.com'));
    }

    public function test_member_cannot_resend_booking_receipt_email(): void
    {
        Mail::fake();

        $member = User::factory()->create(['email' => 'alex@example.com']);
        [$event, $ticketType] = $this->createEventWithTicket();
        $booking = $this->createBooking($member, $event, $ticketType, 'PTR-MEMBER-RECEIPT');

        Sanctum::actingAs($member);

        $this->postJson(route('api.v1.bookings.resend-receipt', $booking))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_admin_downloads_booking_ticket(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create(['name' => 'Alex Puncak', 'email' => 'alex@example.com']);
        [$event, $ticketType] = $this->createEventWithTicket();
        $booking = $this->createBooking($member, $event, $ticketType, 'PTR-TICKET');

        Sanctum::actingAs($admin);

        $response = $this->get(route('api.v1.bookings.ticket', $booking));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('PTR-TICKET')
            ->assertSee('Alex Puncak')
            ->assertSee('General');

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition', ''));
        $this->assertStringContainsString('PTR-TICKET-ticket.html', $response->headers->get('Content-Disposition', ''));
    }

    public function test_payment_status_update_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create();
        [$event, $ticketType] = $this->createEventWithTicket();
        $booking = $this->createBooking($member, $event, $ticketType, 'PTR-STATUS');

        Sanctum::actingAs($member);

        $this->patchJson(route('api.v1.bookings.payment-status.update', $booking), [
            'payment_status' => Booking::PAYMENT_PENDING,
        ])->assertForbidden();

        Sanctum::actingAs($admin);

        $this->patchJson(route('api.v1.bookings.payment-status.update', $booking), [
            'payment_status' => Booking::PAYMENT_PENDING,
        ])
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_PENDING)
            ->assertJsonPath('data.status', Booking::STATUS_PENDING);

        $booking->refresh();

        $this->assertSame(Booking::PAYMENT_PENDING, $booking->payment_status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
    }

    /**
     * @param  array<string, mixed>  $ticketAttributes
     * @return array{0: Event, 1: TicketType}
     */
    private function createEventWithTicket(array $ticketAttributes = [], array $eventAttributes = []): array
    {
        $event = Event::factory()->upcoming()->create($eventAttributes);
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
            'payment_status' => Booking::PAYMENT_PAID,
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
