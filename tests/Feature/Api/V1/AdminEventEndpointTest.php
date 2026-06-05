<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventEndpointTest extends TestCase
{
    public function test_admin_can_create_update_and_delete_event_without_bookings(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $response = $this->postJson(route('api.v1.events.store'), $this->eventPayload([
            'slug' => 'cms-create-event',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cms-create-event')
            ->assertJsonPath('data.publicationStatus', 'draft');

        $event = Event::query()->where('slug', 'cms-create-event')->firstOrFail();
        $this->assertSame(Event::PUBLICATION_DRAFT, $event->publication_status);

        $this->patchJson(route('api.v1.events.update', $event), $this->eventPayload([
            'slug' => 'cms-create-event',
            'status' => 'Published',
            'tickets' => [
                ['id' => 'general-registration', 'name' => 'General Registration', 'price' => 175000, 'stock' => 75],
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.publicationStatus', 'published')
            ->assertJsonPath('data.tickets.0.quantity', 75);

        $this->deleteJson(route('api.v1.events.destroy', $event->refresh()))
            ->assertNoContent();

        $this->assertDatabaseMissing('events', ['slug' => 'cms-create-event']);
    }

    public function test_admin_can_upload_event_cover_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $event = Event::factory()->upcoming()->create([
            'cover_image' => 'events/old-cover.jpg',
            'slug' => 'cover-upload-event',
        ]);
        Storage::disk('public')->put('events/old-cover.jpg', 'old-image');

        $response = $this->post(route('api.v1.events.update', $event), [
            ...$this->eventPayload(['slug' => $event->slug]),
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('new-cover.jpg', 1600, 1000),
        ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('data.imageAlt', 'CMS Create Event');

        $event->refresh();
        $this->assertStringStartsWith('events/', $event->cover_image);
        $this->assertNotSame('events/old-cover.jpg', $event->cover_image);
        Storage::disk('public')->assertExists($event->cover_image);
        Storage::disk('public')->assertMissing('events/old-cover.jpg');
        $this->assertStringContainsString('/storage/events/', $response->json('data.imageUrl'));
    }

    public function test_ticket_capacity_cannot_drop_below_sold_count(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $event = Event::factory()->upcoming()->create(['slug' => 'sold-ticket-event']);
        TicketType::factory()->for($event)->create([
            'public_id' => 'general',
            'name' => 'General',
            'quantity' => 10,
            'sold' => 3,
        ]);

        $this->patchJson(route('api.v1.events.update', $event), $this->eventPayload([
            'slug' => $event->slug,
            'tickets' => [
                ['id' => 'general', 'name' => 'General', 'price' => 100000, 'stock' => 2],
            ],
        ]))
            ->assertStatus(409);

        $this->assertSame(10, $event->ticketTypes()->where('public_id', 'general')->firstOrFail()->quantity);
    }

    public function test_event_with_bookings_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $event = Event::factory()->upcoming()->create(['slug' => 'booking-history-event']);
        Booking::factory()->for($event)->create();

        $this->deleteJson(route('api.v1.events.destroy', $event))
            ->assertStatus(409);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'CMS Create Event',
            'slug' => 'cms-create-event',
            'category' => 'Hike',
            'activity' => 'hike',
            'description' => 'A guided admin-created event.',
            'starts_at' => now()->addMonth()->toIso8601String(),
            'ends_at' => now()->addMonth()->addHours(4)->toIso8601String(),
            'location' => 'Kebun Raya Cibodas',
            'status' => 'draft',
            'tickets' => [
                ['id' => 'general-registration', 'name' => 'General Registration', 'price' => 150000, 'stock' => 100],
            ],
        ], $overrides);
    }
}
