<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Community;
use App\Models\Event;
use App\Models\Place;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingEndpointTest extends TestCase
{
    public function test_authenticated_user_can_create_pending_booking_from_frontend_payload(): void
    {
        $user = User::factory()->create(['name' => 'Alex Puncak', 'email' => 'alex@example.com']);
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();
        $this->fakeMidtransSnap();

        $response = $this->postJson(route('api.v1.bookings.store'), [
            'eventSlug' => $event->slug,
            'termsAccepted' => true,
            'items' => [
                ['ticketTierId' => $ticketType->public_id, 'quantity' => 1],
            ],
            'attendees' => [
                ['name' => 'Alex Puncak', 'email' => 'alex@example.com', 'ticketTierId' => $ticketType->public_id],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_PENDING)
            ->assertJsonPath('data.subtotal', 185000)
            ->assertJsonPath('data.bookingFee', 5000)
            ->assertJsonPath('data.total', 190000)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.paymentProvider', 'midtrans')
            ->assertJsonPath('data.snapToken', 'snap-token');

        $this->assertSame(1, $ticketType->refresh()->sold);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request['transaction_details']['gross_amount'] === 190000
            && $request['customer_details']['email'] === 'alex@example.com'
            && collect($request['item_details'])->contains(fn (array $item): bool => $item['id'] === 'booking-fee'));
    }

    public function test_authenticated_user_can_create_multi_tier_booking_with_resource_ticket_ids(): void
    {
        $user = User::factory()->create(['name' => 'Alex Puncak', 'email' => 'alex@example.com']);
        Sanctum::actingAs($user);
        [$event, $publicTicketType] = $this->createBookableEvent();
        $fallbackTicketType = TicketType::factory()->for($event)->create([
            'public_id' => null,
            'name' => '5K Family Fun',
            'description' => 'Untimed - open to all ages',
            'price' => 95000,
            'quantity' => 10,
            'sold' => 0,
        ]);
        $this->fakeMidtransSnap();

        $response = $this->postJson(route('api.v1.bookings.store'), [
            'eventSlug' => $event->slug,
            'termsAccepted' => true,
            'items' => [
                ['ticketTierId' => $publicTicketType->public_id, 'quantity' => 1],
                ['ticketTierId' => (string) $fallbackTicketType->id, 'quantity' => 2],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.paymentStatus', Booking::PAYMENT_PENDING)
            ->assertJsonPath('data.subtotal', 375000)
            ->assertJsonPath('data.bookingFee', 5000)
            ->assertJsonPath('data.total', 380000)
            ->assertJsonPath('data.snapToken', 'snap-token')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(1, $publicTicketType->refresh()->sold);
        $this->assertSame(2, $fallbackTicketType->refresh()->sold);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request['transaction_details']['gross_amount'] === 380000
            && collect($request['item_details'])->contains(fn (array $item): bool => $item['id'] === (string) $fallbackTicketType->id));
    }

    public function test_booking_conflicts_and_auth_failures_return_stable_json(): void
    {
        [$event, $ticketType] = $this->createBookableEvent(['quantity' => 0]);

        $payload = [
            'eventSlug' => $event->slug,
            'termsAccepted' => true,
            'items' => [
                ['ticketTierId' => $ticketType->public_id, 'quantity' => 1],
            ],
        ];

        $this->postJson(route('api.v1.bookings.store'), $payload)
            ->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.bookings.store'), $payload)
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->postJson(route('api.v1.bookings.store'), [
            ...$payload,
            'eventSlug' => 'not-real',
        ])->assertNotFound();
    }

    public function test_booking_rejects_ticket_identifier_from_another_event(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$event] = $this->createBookableEvent();
        [$otherEvent] = $this->createBookableEvent([], [
            'slug' => 'other-event',
            'title' => 'Other Event',
        ]);
        $otherTicketType = TicketType::factory()->for($otherEvent)->create([
            'public_id' => null,
            'quantity' => 5,
            'sold' => 0,
        ]);

        $this->postJson(route('api.v1.bookings.store'), [
            'eventSlug' => $event->slug,
            'termsAccepted' => true,
            'items' => [
                ['ticketTierId' => (string) $otherTicketType->id, 'quantity' => 1],
            ],
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'One or more ticket tiers are not available for this event.');

        $this->assertSame(0, $otherTicketType->refresh()->sold);
    }

    public function test_account_booking_listing_returns_frontend_cards(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => 'PTR-26-8F3K2A',
            'status' => Booking::STATUS_CONFIRMED,
            'subtotal' => 185000,
            'booking_fee' => 5000,
            'total' => 190000,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $ticketType->price,
        ]);

        $this->getJson(route('api.v1.bookings.index', ['status' => 'upcoming']))
            ->assertOk()
            ->assertJsonPath('data.0.id', 'PTR-26-8F3K2A')
            ->assertJsonPath('data.0.status', 'upcoming')
            ->assertJsonPath('data.0.title', $event->title)
            ->assertJsonPath('data.0.primaryAction', 'View ticket');
    }

    public function test_cancelled_booking_appears_as_past_in_all_status_listing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => 'PTR-26-CANCELLED',
            'status' => Booking::STATUS_CANCELLED,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $ticketType->price,
        ]);

        $this->getJson(route('api.v1.bookings.index', ['status' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'past')
            ->assertJsonPath('data.0.badge', 'Cancelled')
            ->assertJsonPath('data.0.primaryAction', 'View details');
    }

    public function test_refunded_booking_appears_as_past_in_all_status_listing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => 'PTR-26-REFUNDED',
            'status' => Booking::STATUS_REFUNDED,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $ticketType->price,
        ]);

        $this->getJson(route('api.v1.bookings.index', ['status' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.0.status', 'past')
            ->assertJsonPath('data.0.badge', 'Refunded')
            ->assertJsonPath('data.0.primaryAction', 'View details');
    }

    public function test_cancelled_booking_excluded_from_upcoming_status_listing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => 'PTR-26-CANCELLED-UPCOMING',
            'status' => Booking::STATUS_CANCELLED,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $ticketType->price,
        ]);

        $this->getJson(route('api.v1.bookings.index', ['status' => 'upcoming']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_booking_cancellation_enforces_24_hour_cutoff(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent([
            'quantity' => 10,
            'sold' => 1,
        ], [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);
        $booking = Booking::factory()->for($user)->for($event)->create([
            'reference' => 'PTR-26-CANCEL',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => $ticketType->price,
        ]);

        $this->postJson(route('api.v1.bookings.cancel', $booking), ['reason' => 'Cannot attend'])
            ->assertOk()
            ->assertJsonPath('data.status', Booking::STATUS_CANCELLED);
        $this->assertSame(0, $ticketType->refresh()->sold);

        [$lateEvent, $lateTicketType] = $this->createBookableEvent([], [
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(24),
        ]);
        $lateBooking = Booking::factory()->for($user)->for($lateEvent)->create([
            'reference' => 'PTR-26-LATE',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $lateBooking->items()->create([
            'ticket_type_id' => $lateTicketType->id,
            'quantity' => 1,
            'unit_price' => $lateTicketType->price,
        ]);

        $this->postJson(route('api.v1.bookings.cancel', $lateBooking), ['reason' => 'Too late'])
            ->assertStatus(409);
    }

    /**
     * @param  array<string, mixed>  $ticketAttributes
     * @param  array<string, mixed>  $eventAttributes
     * @return array{0: Event, 1: TicketType}
     */
    private function createBookableEvent(array $ticketAttributes = [], array $eventAttributes = []): array
    {
        $community = Community::factory()->create();
        $place = Place::factory()->for($community)->create();
        $event = Event::factory()
            ->for($community)
            ->for($place)
            ->upcoming()
            ->create(array_merge([
                'public_id' => fake()->unique()->bothify('evt_###'),
                'slug' => fake()->unique()->slug(),
                'title' => 'Puncak Trail Run 2026',
                'location' => 'Gunung Pangrango, Bogor',
                'full_date_label' => 'Saturday, 14 June 2026',
            ], $eventAttributes));
        $ticketType = TicketType::factory()->for($event)->create(array_merge([
            'public_id' => '21k',
            'name' => '21K Mountain Trail',
            'description' => 'Timed - finisher medal - trail support',
            'price' => 185000,
            'quantity' => 42,
            'sold' => 0,
        ], $ticketAttributes));

        return [$event, $ticketType];
    }

    private function fakeMidtransSnap(): void
    {
        config()->set('services.midtrans.server_key', 'SB-Mid-server-test');
        config()->set('services.midtrans.snap_api_url', 'https://app.sandbox.midtrans.com/snap/v1/transactions');

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token',
            ], 201),
        ]);
    }
}
