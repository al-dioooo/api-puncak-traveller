<?php

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\ContactMethod;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Place;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicApiEndpointTest extends TestCase
{
    public function test_events_index_matches_frontend_contract(): void
    {
        $event = $this->createEventWithTicket([
            'public_id' => 'evt_morning_run',
            'slug' => 'morning-run',
            'title' => 'Morning Run',
            'activity' => Event::ACTIVITY_TRAIL_RUN,
            'activity_type' => Event::ACTIVITY_TRAIL_RUN,
            'category' => 'Trail Run',
            'cover_image' => '/events/morning-run.jpg',
            'image_alt' => 'Trail runners at sunrise',
            'location' => 'Gunung Pangrango, Bogor',
            'region' => 'West Java',
            'price_label' => 'From Rp 95K',
            'spots_label' => '20 spots left',
        ], [
            'public_id' => 'general',
            'price' => 95000,
            'quantity' => 20,
            'sold' => 0,
        ]);

        $this->getJson(route('api.v1.events.index', [
            'activity' => 'trail-run',
            'q' => 'Morning',
            'status' => 'upcoming',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', 'evt_morning_run')
            ->assertJsonPath('data.0.slug', $event->slug)
            ->assertJsonPath('data.0.activity', 'trail-run')
            ->assertJsonPath('data.0.category', 'Trail Run')
            ->assertJsonPath('data.0.priceFrom', 95000)
            ->assertJsonPath('data.0.imageUrl', '/events/morning-run.jpg')
            ->assertJsonStructure(['data' => [['createdAt']]])
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.perPage', 12)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_events_default_order_prioritizes_newest_upcoming_and_completed_last(): void
    {
        $this->createEventWithTicket([
            'slug' => 'old-upcoming',
            'title' => 'Old Upcoming',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
            'created_at' => now()->subDays(5),
        ]);
        $this->createEventWithTicket([
            'slug' => 'new-completed',
            'title' => 'New Completed',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'created_at' => now(),
        ]);
        $this->createEventWithTicket([
            'slug' => 'live-now',
            'title' => 'Live Now',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'created_at' => now()->subHour(),
        ]);
        $this->createEventWithTicket([
            'slug' => 'new-upcoming',
            'title' => 'New Upcoming',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(3),
            'created_at' => now()->subHour(),
        ]);

        $this->getJson(route('api.v1.events.index', ['per_page' => 10]))
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'new-upcoming')
            ->assertJsonPath('data.1.slug', 'old-upcoming')
            ->assertJsonPath('data.2.slug', 'live-now')
            ->assertJsonPath('data.3.slug', 'new-completed');
    }

    public function test_events_can_be_filtered_by_activity_search_and_status(): void
    {
        $this->createEventWithTicket([
            'slug' => 'lakeside-camp-weekend',
            'title' => 'Lakeside Camp Weekend',
            'activity' => Event::ACTIVITY_CAMPING,
            'activity_type' => Event::ACTIVITY_CAMPING,
            'category' => 'Camping',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addDays(2),
        ]);
        $this->createEventWithTicket([
            'slug' => 'future-camp',
            'title' => 'Future Camp',
            'activity' => Event::ACTIVITY_CAMPING,
            'activity_type' => Event::ACTIVITY_CAMPING,
            'category' => 'Camping',
        ]);

        $this->getJson(route('api.v1.events.index', [
            'activity' => 'camping',
            'q' => 'lakeside',
            'status' => 'completed',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lakeside Camp Weekend');

        $this->getJson(route('api.v1.events.index', ['activity' => 'invalid']))
            ->assertUnprocessable();
    }

    public function test_communities_index_exposes_mobile_browse_counts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('communities/demo/puncak-menginap.jpg', 'demo-image-bytes');

        $menginap = Community::factory()->create([
            'name' => 'Puncak Menginap',
            'slug' => 'puncak-menginap',
            'image_path' => 'communities/demo/puncak-menginap.jpg',
            'member_count' => 5200,
        ]);
        $runners = Community::factory()->create([
            'name' => 'Puncak Runners',
            'slug' => 'puncak-runners',
            'member_count' => 3200,
        ]);
        Community::factory()->create([
            'name' => 'Puncak In',
            'slug' => 'puncak-in',
            'member_count' => 1400,
        ]);
        Community::factory()->create(['slug' => 'puncak-travellers', 'member_count' => 18400]);

        Place::factory()->for($menginap)->create();
        Event::factory()->for($menginap)->upcoming()->create(['slug' => 'menginap-event']);
        Event::factory()->for($runners)->upcoming()->create(['slug' => 'runner-event']);

        $this->getJson(route('api.v1.communities.index', ['per_page' => 10]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'puncak-menginap')
            ->assertJsonPath('data.0.places_count', 1)
            ->assertJsonPath('data.0.events_count', 1)
            ->assertJsonPath('data.0.placesCount', 1)
            ->assertJsonPath('data.0.eventsCount', 1)
            ->assertJsonPath('data.0.image_url', '/storage/communities/demo/puncak-menginap.jpg')
            ->assertJsonPath('data.1.slug', 'puncak-runners')
            ->assertJsonPath('data.1.places_count', 0)
            ->assertJsonPath('data.1.events_count', 1)
            ->assertJsonPath('data.2.slug', 'puncak-in');
    }

    public function test_events_can_be_filtered_by_community_slug(): void
    {
        $runners = Community::factory()->create(['slug' => 'puncak-runners']);
        $menginap = Community::factory()->create(['slug' => 'puncak-menginap']);
        Event::factory()->for($runners)->upcoming()->create([
            'slug' => 'runner-only',
            'title' => 'Runner Only',
        ]);
        Event::factory()->for($menginap)->upcoming()->create([
            'slug' => 'menginap-only',
            'title' => 'Menginap Only',
        ]);

        $this->getJson(route('api.v1.events.index', [
            'community' => 'puncak-runners',
            'per_page' => 10,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'runner-only');
    }

    public function test_places_index_exposes_seeded_image_contract(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('places/demo/villa.jpg', 'demo-image-bytes');
        $community = Community::factory()->create(['slug' => 'puncak-menginap']);
        Place::factory()->for($community)->create([
            'name' => 'Seeded Villa',
            'image_path' => 'places/demo/villa.jpg',
            'image_alt' => 'Seeded villa in Puncak',
        ]);

        $this->getJson(route('api.v1.places.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Seeded Villa')
            ->assertJsonPath('data.0.image_path', 'places/demo/villa.jpg')
            ->assertJsonPath('data.0.imageUrl', '/storage/places/demo/villa.jpg')
            ->assertJsonPath('data.0.image_url', '/storage/places/demo/villa.jpg')
            ->assertJsonPath('data.0.imageAlt', 'Seeded villa in Puncak');
    }

    public function test_public_clients_can_view_event_details_with_ticket_types(): void
    {
        $event = $this->createEventWithTicket([
            'public_id' => 'evt_camp_fun_run',
            'slug' => 'camp-fun-run',
            'title' => 'Camp Fun Run',
            'summary' => ['Run through camp trails.'],
            'includes' => ['Race bib'],
            'schedule' => [['time' => '06:00', 'title' => 'Flag-off']],
        ], [
            'public_id' => 'general',
            'name' => 'General Admission',
            'description' => 'Standard participant access',
            'price' => 175000,
            'quantity' => 25,
            'sold' => 5,
            'capacity_label' => '20 left',
        ]);

        $this->getJson(route('api.v1.events.show', $event))
            ->assertOk()
            ->assertJsonPath('data.id', 'evt_camp_fun_run')
            ->assertJsonPath('data.slug', 'camp-fun-run')
            ->assertJsonPath('data.priceFrom', 175000)
            ->assertJsonPath('data.tickets.0.id', 'general')
            ->assertJsonPath('data.tickets.0.stock', 20)
            ->assertJsonPath('data.schedule.0.title', 'Flag-off');
    }

    public function test_gallery_and_contact_methods_match_frontend_contracts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/demo/bonfire-stargazing.jpg', 'demo-image-bytes');
        $community = Community::factory()->create(['slug' => 'puncak-runners']);
        Gallery::factory()->for($community)->create([
            'public_id' => 'bonfire-stargazing',
            'title' => 'Bonfire & stargazing',
            'event_label' => 'Highland Camp',
            'category' => 'camping',
            'year' => '2026',
            'image_path' => 'gallery/demo/bonfire-stargazing.jpg',
            'image_alt' => 'Campers gathering near a warm highland bonfire',
        ]);
        Gallery::factory()->for($community)->create(['category' => 'hike']);
        ContactMethod::factory()->create([
            'title' => 'Email us',
            'value' => 'halo@puncaktravellers.id',
            'description' => 'For event questions, partnerships, and media.',
            'sort_order' => 1,
        ]);

        $this->getJson(route('api.v1.galleries.index', ['category' => 'camping']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'bonfire-stargazing')
            ->assertJsonPath('data.0.imageUrl', url('/storage/gallery/demo/bonfire-stargazing.jpg'))
            ->assertJsonPath('meta.total', 1);

        $this->getJson(route('api.v1.contact-methods.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Email us')
            ->assertJsonPath('data.0.value', 'halo@puncaktravellers.id');
    }

    public function test_public_clients_can_read_populated_landing_payload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/demo/puncak-trail-run.jpg', 'demo-image-bytes');
        User::factory()->count(2)->create(['role' => User::ROLE_MEMBER]);
        $parentCommunity = Community::factory()->create(['slug' => 'puncak-parent']);
        Community::factory()->for($parentCommunity, 'parent')->create([
            'slug' => 'puncak-runners',
            'image_path' => '/landing/community-runners.jpg',
        ]);
        $event = $this->createEventWithTicket([
            'title' => 'Puncak Trail Run',
            'slug' => 'puncak-trail-run',
            'activity' => Event::ACTIVITY_TRAIL_RUN,
            'activity_type' => Event::ACTIVITY_TRAIL_RUN,
            'category' => 'Trail Run',
            'cover_image' => '/events/puncak-trail-run.jpg',
        ], [
            'price' => 185000,
        ]);
        Gallery::factory()->create([
            'event_id' => $event->id,
            'category' => 'trail-run',
            'image_path' => 'gallery/demo/puncak-trail-run.jpg',
        ]);

        $this->getJson(route('api.v1.landing'))
            ->assertOk()
            ->assertJsonPath('data.hero_stats.0.value', 2)
            ->assertJsonPath('data.upcoming_events.0.slug', 'puncak-trail-run')
            ->assertJsonPath('data.upcoming_events.0.priceFrom', 185000)
            ->assertJsonPath('data.communities.0.image_url', '/landing/community-runners.jpg')
            ->assertJsonPath('data.activities.0.activity_type', Event::ACTIVITY_TRAIL_RUN);
    }

    /**
     * @param  array<string, mixed>  $eventAttributes
     * @param  array<string, mixed>  $ticketAttributes
     */
    private function createEventWithTicket(array $eventAttributes = [], array $ticketAttributes = []): Event
    {
        $community = Community::factory()->create(['slug' => fake()->unique()->slug()]);
        $place = Place::factory()->for($community)->create();
        $event = Event::factory()
            ->for($community)
            ->for($place)
            ->upcoming()
            ->create($eventAttributes);

        TicketType::factory()
            ->for($event)
            ->create(array_merge([
                'public_id' => 'general',
                'name' => 'General Admission',
                'description' => 'Standard participant access',
                'price' => 95000,
                'quantity' => 10,
                'sold' => 0,
                'currency' => 'IDR',
            ], $ticketAttributes));

        return $event;
    }
}
