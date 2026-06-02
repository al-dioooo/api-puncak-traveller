<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Community;
use App\Models\Event;
use App\Models\Place;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_create_confirmed_booking_from_frontend_payload(): void
    {
        $user = User::factory()->create(['name' => 'Alex Puncak', 'email' => 'alex@example.com']);
        Sanctum::actingAs($user);
        [$event, $ticketType] = $this->createBookableEvent();

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
            ->assertJsonPath('data.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('data.subtotal', 185000)
            ->assertJsonPath('data.bookingFee', 5000)
            ->assertJsonPath('data.total', 190000)
            ->assertJsonPath('data.currency', 'IDR');

        $this->assertSame(1, $ticketType->refresh()->sold);
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
}
