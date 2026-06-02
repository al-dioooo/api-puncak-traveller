<?php

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Place;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicApiEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_clients_can_list_and_filter_events(): void
    {
        $community = Community::factory()->create(['slug' => 'puncak-runners']);
        $place = Place::factory()->for($community)->create();
        $upcomingEvent = Event::factory()->for($community)->for($place)->upcoming()->create([
            'slug' => 'morning-run',
            'title' => 'Morning Run',
        ]);
        Event::factory()->for($community)->for($place)->past()->create([
            'slug' => 'past-walk',
            'title' => 'Past Walk',
        ]);

        $response = $this->getJson(route('api.v1.events.index', [
            'status' => 'upcoming',
            'community' => 'puncak-runners',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $upcomingEvent->id)
            ->assertJsonPath('data.0.status', 'upcoming')
            ->assertJsonPath('data.0.community.slug', 'puncak-runners');
    }

    public function test_public_clients_can_view_event_details_with_ticket_types(): void
    {
        $event = Event::factory()->upcoming()->create(['slug' => 'camp-fun-run']);
        $ticketType = TicketType::factory()->for($event)->create([
            'name' => 'General Admission',
            'quantity' => 25,
            'sold' => 5,
        ]);

        $response = $this->getJson(route('api.v1.events.show', $event));

        $response
            ->assertOk()
            ->assertJsonPath('data.slug', 'camp-fun-run')
            ->assertJsonPath('data.ticket_types.0.id', $ticketType->id)
            ->assertJsonPath('data.ticket_types.0.remaining', 20);
    }

    public function test_public_clients_can_read_communities_places_and_galleries(): void
    {
        $community = Community::factory()->create(['slug' => 'puncak-travellers']);
        $child = Community::factory()->for($community, 'parent')->create(['slug' => 'puncak-runners']);
        $place = Place::factory()->for($community)->create();
        $gallery = Gallery::factory()->for($community)->create(['event_id' => null]);

        $this->getJson(route('api.v1.communities.index'))
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'puncak-travellers')
            ->assertJsonPath('data.0.children.0.id', $child->id);

        $this->getJson(route('api.v1.places.index', ['community' => 'puncak-travellers']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $place->id);

        $this->getJson(route('api.v1.galleries.index', ['community' => 'puncak-travellers']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $gallery->id);
    }

    public function test_public_clients_can_submit_contact_messages(): void
    {
        $response = $this->postJson(route('api.v1.contact.store'), [
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'message' => 'I want to know more about the next event.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');
    }

    public function test_contact_message_requires_valid_payload(): void
    {
        $response = $this->postJson(route('api.v1.contact.store'), [
            'name' => '',
            'email' => 'invalid',
            'message' => '',
        ]);

        $response->assertUnprocessable();
    }
}
