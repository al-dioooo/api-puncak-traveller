<?php

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Place;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicApiEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://api-puncak-traveller.test',
            'filesystems.disks.public.url' => 'http://api-puncak-traveller.test/storage',
        ]);
    }

    public function test_public_clients_can_list_and_filter_events(): void
    {
        $community = Community::factory()->create(['slug' => 'puncak-runners']);
        $place = Place::factory()->for($community)->create();
        $upcomingEvent = Event::factory()->for($community)->for($place)->upcoming()->create([
            'slug' => 'morning-run',
            'title' => 'Morning Run',
            'activity_type' => Event::ACTIVITY_TRAIL_RUN,
            'distance_label' => '10K',
            'cover_image' => 'events/morning-run.jpg',
        ]);
        TicketType::factory()->for($upcomingEvent)->create(['price' => 125000]);
        TicketType::factory()->for($upcomingEvent)->create(['price' => 95000]);
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
            ->assertJsonPath('data.0.community.slug', 'puncak-runners')
            ->assertJsonPath('data.0.activity_type', Event::ACTIVITY_TRAIL_RUN)
            ->assertJsonPath('data.0.activity_label', 'Trail Run')
            ->assertJsonPath('data.0.distance_label', '10K')
            ->assertJsonPath('data.0.starting_price', 95000)
            ->assertJsonPath('data.0.cover_image', 'events/morning-run.jpg')
            ->assertJsonPath('data.0.cover_image_url', 'http://api-puncak-traveller.test/storage/events/morning-run.jpg');
    }

    public function test_public_clients_can_view_event_details_with_ticket_types(): void
    {
        $event = Event::factory()->upcoming()->create([
            'slug' => 'camp-fun-run',
            'cover_image' => 'events/camp-fun-run.jpg',
        ]);
        $ticketType = TicketType::factory()->for($event)->create([
            'name' => 'General Admission',
            'price' => 175000,
            'quantity' => 25,
            'sold' => 5,
        ]);

        $response = $this->getJson(route('api.v1.events.show', $event));

        $response
            ->assertOk()
            ->assertJsonPath('data.slug', 'camp-fun-run')
            ->assertJsonPath('data.starting_price', 175000)
            ->assertJsonPath('data.cover_image_url', 'http://api-puncak-traveller.test/storage/events/camp-fun-run.jpg')
            ->assertJsonPath('data.ticket_types.0.id', $ticketType->id)
            ->assertJsonPath('data.ticket_types.0.remaining', 20);
    }

    public function test_public_clients_can_read_communities_places_and_galleries(): void
    {
        $community = Community::factory()->create([
            'slug' => 'puncak-travellers',
            'image_path' => 'communities/puncak-travellers.jpg',
            'member_count' => 18000,
        ]);
        $child = Community::factory()->for($community, 'parent')->create(['slug' => 'puncak-runners']);
        $place = Place::factory()->for($community)->create();
        $gallery = Gallery::factory()->for($community)->create([
            'event_id' => null,
            'image_path' => 'galleries/morning-climb.jpg',
        ]);

        $this->getJson(route('api.v1.communities.index'))
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'puncak-travellers')
            ->assertJsonPath('data.0.image_path', 'communities/puncak-travellers.jpg')
            ->assertJsonPath('data.0.image_url', 'http://api-puncak-traveller.test/storage/communities/puncak-travellers.jpg')
            ->assertJsonPath('data.0.member_count', 18000)
            ->assertJsonPath('data.0.children.0.id', $child->id);

        $this->getJson(route('api.v1.places.index', ['community' => 'puncak-travellers']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $place->id);

        $this->getJson(route('api.v1.galleries.index', ['community' => 'puncak-travellers']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $gallery->id)
            ->assertJsonPath('data.0.image_path', 'galleries/morning-climb.jpg')
            ->assertJsonPath('data.0.image_url', 'http://api-puncak-traveller.test/storage/galleries/morning-climb.jpg');
    }

    public function test_public_clients_can_read_empty_landing_payload(): void
    {
        $response = $this->getJson(route('api.v1.landing'));

        $response
            ->assertOk()
            ->assertJsonPath('data.hero_stats.0.label', 'Active members')
            ->assertJsonPath('data.hero_stats.0.value', 0)
            ->assertJsonPath('data.upcoming_events', [])
            ->assertJsonPath('data.activities.0.activity_type', Event::ACTIVITY_TRAIL_RUN)
            ->assertJsonPath('data.activities.0.upcoming_count', 0)
            ->assertJsonPath('data.live_event', null)
            ->assertJsonPath('data.communities', [])
            ->assertJsonPath('data.gallery', []);
    }

    public function test_public_clients_can_read_populated_landing_payload(): void
    {
        User::factory()->count(2)->create(['role' => User::ROLE_MEMBER]);
        $community = Community::factory()->create(['slug' => 'puncak-travellers']);
        $childCommunity = Community::factory()->for($community, 'parent')->create([
            'name' => 'Puncak Runners',
            'slug' => 'puncak-runners',
            'image_path' => 'communities/puncak-runners.jpg',
            'member_count' => 3200,
        ]);
        $place = Place::factory()->for($childCommunity, 'community')->create(['name' => 'Gunung Pangrango']);
        $upcomingEvent = Event::factory()->for($childCommunity, 'community')->for($place)->upcoming()->create([
            'title' => 'Puncak Trail Run',
            'slug' => 'puncak-trail-run',
            'activity_type' => Event::ACTIVITY_TRAIL_RUN,
            'distance_label' => '15K',
            'cover_image' => 'events/puncak-trail-run.jpg',
        ]);
        TicketType::factory()->for($upcomingEvent)->create(['price' => 185000]);
        $liveEvent = Event::factory()->for($childCommunity, 'community')->for($place)->ongoing()->create([
            'title' => 'Forest Fun Run',
            'slug' => 'forest-fun-run',
            'activity_type' => Event::ACTIVITY_HEALTHY_WALK,
            'distance_label' => '10K',
        ]);
        Gallery::factory()->for($childCommunity, 'community')->for($upcomingEvent)->create([
            'image_path' => 'galleries/puncak-trail-run.jpg',
            'caption' => 'Morning climb',
        ]);

        $response = $this->getJson(route('api.v1.landing'));

        $response
            ->assertOk()
            ->assertJsonPath('data.hero_stats.0.value', 2)
            ->assertJsonPath('data.upcoming_events.0.slug', 'puncak-trail-run')
            ->assertJsonPath('data.upcoming_events.0.starting_price', 185000)
            ->assertJsonPath('data.upcoming_events.0.cover_image_url', 'http://api-puncak-traveller.test/storage/events/puncak-trail-run.jpg')
            ->assertJsonPath('data.activities.0.activity_type', Event::ACTIVITY_TRAIL_RUN)
            ->assertJsonPath('data.activities.0.upcoming_count', 1)
            ->assertJsonPath('data.live_event.slug', 'forest-fun-run')
            ->assertJsonPath('data.live_event.distance_label', '10K')
            ->assertJsonPath('data.communities.0.slug', 'puncak-runners')
            ->assertJsonPath('data.communities.0.image_url', 'http://api-puncak-traveller.test/storage/communities/puncak-runners.jpg')
            ->assertJsonPath('data.gallery.0.caption', 'Morning climb')
            ->assertJsonPath('data.gallery.0.image_url', 'http://api-puncak-traveller.test/storage/galleries/puncak-trail-run.jpg');
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
