<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Community;
use App\Models\ContactMethod;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Place;
use App\Models\SavedEvent;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $communitySeedData = [
            'puncak-menginap' => [
                'name' => 'Puncak Menginap',
                'description' => 'Curated villas, cabins, and cool-weather stays for slow highland weekends.',
                'image' => 'puncak-menginap.jpg',
                'member_count' => 5200,
            ],
            'puncak-runners' => [
                'name' => 'Puncak Runners',
                'description' => 'Trail and road-running crew for sunrise starts across the Puncak ridge.',
                'image' => 'puncak-runners.jpg',
                'member_count' => 3200,
            ],
            'puncak-in' => [
                'name' => 'Puncak In',
                'description' => 'Small-group campouts, bonfires, and outdoor gatherings in the highlands.',
                'image' => 'puncak-in.jpg',
                'member_count' => 1400,
            ],
        ];

        $communities = [];

        foreach ($communitySeedData as $slug => $communityData) {
            $storagePath = $this->copySeedAsset('communities', $communityData['image']);

            $communities[$slug] = Community::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'parent_id' => null,
                    'name' => $communityData['name'],
                    'description' => $communityData['description'],
                    'image_path' => $storagePath,
                    'member_count' => $communityData['member_count'],
                ]
            );
        }

        $places = [];
        $placeSeedData = [
            ['lembah-pinang-villa', 'Lembah Pinang Villa', -6.6971000, 106.9924000, 'A warm timber villa facing tea terraces and morning mist.', 'lembah-pinang-villa.jpg'],
            ['cibodas-glass-lodge', 'Cibodas Glass Lodge', -6.7382000, 107.0031000, 'A glass-fronted lodge for small groups who want sunrise views from bed.', 'cibodas-glass-lodge.jpg'],
            ['bukit-embun-cabin', 'Bukit Embun Cabin', -6.7093000, 106.9845000, 'A compact A-frame cabin tucked beside pine trees and quiet footpaths.', 'bukit-embun-cabin.jpg'],
            ['tea-valley-residence', 'Tea Valley Residence', -6.6864000, 106.9762000, 'A family-friendly residence with a broad lawn and mountain backdrop.', 'tea-valley-residence.jpg'],
            ['ciloto-family-villa', 'Ciloto Family Villa', -6.7165000, 107.0127000, 'A row of bright villas near Ciloto for weekend family stays.', 'ciloto-family-villa.jpg'],
            ['riverside-pine-house', 'Riverside Pine House', -6.7248000, 106.9653000, 'A quiet pine house with open decks, cool air, and river sound.', 'riverside-pine-house.jpg'],
            ['meadow-view-cottage', 'Meadow View Cottage', -6.7042000, 106.9559000, 'A cottage overlooking open meadows and layered Puncak hills.', 'meadow-view-cottage.jpg'],
        ];

        foreach ($placeSeedData as [$slug, $name, $lat, $lng, $description, $imageFile]) {
            $storagePath = $this->copySeedAsset('places', $imageFile);

            $places[$slug] = Place::query()->updateOrCreate(
                ['name' => $name],
                [
                    'community_id' => $communities['puncak-menginap']->id,
                    'lat' => $lat,
                    'lng' => $lng,
                    'description' => $description,
                    'image_path' => $storagePath,
                    'image_alt' => $name.' in the Puncak highlands',
                ]
            );
        }

        Place::query()
            ->whereIn('community_id', [$communities['puncak-runners']->id, $communities['puncak-in']->id])
            ->delete();

        $events = [
            ['community' => 'puncak-menginap', 'place' => 'lembah-pinang-villa', 'id' => 'evt_stay_001', 'slug' => 'puncak-menginap-open-house', 'title' => 'Puncak Menginap Open House', 'category' => 'Stay Tour', 'activity' => 'wellness', 'starts_at' => '2026-06-20 10:00:00', 'ends_at' => '2026-06-20 16:00:00', 'date_label' => 'Sat, 20 Jun - 10:00', 'full_date_label' => 'Saturday, 20 June 2026', 'time_label' => 'Open house 10:00 WIB', 'location' => 'Lembah Pinang Villa', 'region' => 'Bogor, West Java', 'price_label' => 'From Rp 75K', 'spots_label' => '40 spots left', 'cover_image' => 'puncak-menginap-open-house.jpg', 'image_alt' => 'Guests touring a warm highland villa in Puncak', 'distance_label' => 'Villa Tour', 'tickets' => [['id' => 'tour-pass', 'name' => 'Tour Pass', 'description' => 'Villa tour and welcome drink', 'price' => 75000, 'stock' => 40, 'capacity' => '40 left']]],
            ['community' => 'puncak-menginap', 'place' => 'cibodas-glass-lodge', 'id' => 'evt_stay_002', 'slug' => 'villa-hosting-workshop', 'title' => 'Villa Hosting Workshop', 'category' => 'Workshop', 'activity' => 'wellness', 'starts_at' => '2026-07-12 09:00:00', 'ends_at' => '2026-07-12 13:00:00', 'date_label' => 'Sun, 12 Jul - 09:00', 'full_date_label' => 'Sunday, 12 July 2026', 'time_label' => 'Workshop 09:00 WIB', 'location' => 'Cibodas Glass Lodge', 'region' => 'Bogor, West Java', 'price_label' => 'From Rp 125K', 'spots_label' => '24 spots left', 'cover_image' => 'villa-hosting-workshop.jpg', 'image_alt' => 'Highland villa hosts preparing a guest workshop', 'distance_label' => 'Hosting', 'tickets' => [['id' => 'workshop', 'name' => 'Workshop Seat', 'description' => 'Workshop access and lunch', 'price' => 125000, 'stock' => 24, 'capacity' => '24 left']]],
            [
                'community' => 'puncak-runners',
                'id' => 'evt_001',
                'slug' => 'puncak-trail-run-2026',
                'title' => 'Puncak Trail Run 2026',
                'category' => 'Trail Run',
                'activity' => 'trail-run',
                'starts_at' => '2026-06-14 06:00:00',
                'ends_at' => '2026-06-14 11:00:00',
                'date_label' => 'Sat, 14 Jun - 06:00',
                'full_date_label' => 'Saturday, 14 June 2026',
                'time_label' => 'Flag-off 06:00 WIB',
                'location' => 'Gunung Pangrango, Bogor',
                'region' => 'West Java',
                'price_label' => 'From Rp 185K',
                'spots_label' => '42 spots left',
                'cover_image' => 'puncak-trail-run-2026.jpg',
                'image_alt' => 'Trail runners crossing a misty highland ridge near Bogor',
                'distance_label' => '21K - 10K - 5K',
                'elevation_label' => '+1,250 m gain',
                'difficulty' => 'Moderate-hard',
                'venue_name' => 'Pangrango Base Camp, Bogor',
                'venue_description' => 'A marked mountain route through pine forest, tea plantations, and misty ridgelines.',
                'summary' => [
                    'Halo, trail lovers! Puncak Trail Run 2026 returns to the slopes of Gunung Pangrango for our biggest sunrise edition yet.',
                    'Wind through pine forest, tea plantations and misty ridgelines on a fully-marked, community-supported course, then celebrate at the finish with brunch and good company.',
                    'Whether you are chasing a 21K personal best or walking the 5K with family, there is a distance for every pace. All proceeds support trail conservation in the Pangrango national park.',
                ],
                'includes' => [
                    'Race bib & timing chip',
                    'Finisher medal & e-certificate',
                    'Eco race pack with no single-use plastic',
                    'Trail marshals & medical support',
                    'Free event photos',
                    'Refreshments at the finish',
                ],
                'schedule' => [
                    ['time' => '04:30', 'title' => 'Gate & check-in opens'],
                    ['time' => '05:45', 'title' => 'Warm-up & briefing'],
                    ['time' => '06:00', 'title' => '21K flag-off'],
                    ['time' => '06:15', 'title' => '10K & 5K flag-off'],
                    ['time' => '09:30', 'title' => 'Awards & community brunch'],
                ],
                'tickets' => [
                    ['id' => '21k', 'name' => '21K Mountain Trail', 'description' => 'Timed - finisher medal - trail support', 'price' => 185000, 'stock' => 42, 'capacity' => '42 left'],
                    ['id' => '10k', 'name' => '10K Forest Loop', 'description' => 'Timed - finisher medal', 'price' => 150000, 'stock' => 88, 'capacity' => '88 left'],
                    ['id' => '5k', 'name' => '5K Family Fun', 'description' => 'Untimed - open to all ages', 'price' => 95000, 'stock' => 110, 'capacity' => '110 left'],
                ],
            ],
            ['community' => 'puncak-runners', 'id' => 'evt_002', 'slug' => 'sunrise-healthy-walk', 'title' => 'Sunrise Healthy Walk', 'category' => 'Walk', 'activity' => 'walk', 'starts_at' => '2026-06-22 05:30:00', 'ends_at' => '2026-06-22 09:00:00', 'date_label' => 'Sun, 22 Jun - 05:30', 'full_date_label' => 'Sunday, 22 June 2026', 'time_label' => 'Start 05:30 WIB', 'location' => 'Kebun Raya Cibodas', 'region' => 'West Java', 'price_label' => 'From Free', 'spots_label' => '120 spots left', 'cover_image' => 'sunrise-healthy-walk.jpg', 'image_alt' => 'A warm sunrise over a quiet mountain walking route', 'distance_label' => '5K', 'tickets' => [['id' => 'general', 'name' => 'General Entry', 'description' => 'Community walk access', 'price' => 0, 'stock' => 120, 'capacity' => '120 left']]],
            ['community' => 'puncak-runners', 'id' => 'evt_004', 'slug' => 'forest-fun-run-10k', 'title' => 'Forest Fun Run 10K', 'category' => 'Fun Run', 'activity' => 'fun-run', 'starts_at' => now()->subHour()->format('Y-m-d H:i:s'), 'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'), 'date_label' => 'Today - 07:00', 'full_date_label' => 'Happening today', 'time_label' => 'Started 07:00 WIB', 'location' => 'Taman Hutan Raya, Bandung', 'region' => 'West Java', 'price_label' => 'From Rp 120K', 'spots_label' => 'Sold out', 'cover_image' => 'forest-fun-run-10k.jpg', 'image_alt' => 'Runners moving through a forest route during a live event', 'distance_label' => '10K', 'tickets' => [['id' => 'general', 'name' => 'General Entry', 'description' => 'Standard participant access', 'price' => 120000, 'stock' => 0, 'capacity' => 'Sold out']]],
            ['community' => 'puncak-runners', 'id' => 'evt_005', 'slug' => 'misty-ridge-hike', 'title' => 'Misty Ridge Hike', 'category' => 'Hike', 'activity' => 'hike', 'starts_at' => '2026-06-28 06:30:00', 'ends_at' => '2026-06-28 12:00:00', 'date_label' => 'Sat, 28 Jun - 06:30', 'full_date_label' => 'Saturday, 28 June 2026', 'time_label' => 'Start 06:30 WIB', 'location' => 'Gunung Papandayan', 'region' => 'West Java', 'price_label' => 'From Rp 150K', 'spots_label' => '30 spots left', 'cover_image' => 'misty-ridge-hike.jpg', 'image_alt' => 'A mountain ridge path covered with soft morning mist', 'distance_label' => 'Hike', 'tickets' => [['id' => 'general', 'name' => 'General Entry', 'description' => 'Guided hike access', 'price' => 150000, 'stock' => 30, 'capacity' => '30 left']]],
            ['community' => 'puncak-runners', 'id' => 'evt_006', 'slug' => 'mindful-mountain-yoga', 'title' => 'Mindful Mountain Yoga', 'category' => 'Wellness', 'activity' => 'wellness', 'starts_at' => '2026-06-29 07:00:00', 'ends_at' => '2026-06-29 10:00:00', 'date_label' => 'Sun, 29 Jun - 07:00', 'full_date_label' => 'Sunday, 29 June 2026', 'time_label' => 'Start 07:00 WIB', 'location' => 'Bukit Moko, Bandung', 'region' => 'West Java', 'price_label' => 'From Rp 95K', 'spots_label' => '25 spots left', 'cover_image' => 'mindful-mountain-yoga.jpg', 'image_alt' => 'A quiet highland view used for morning wellness activities', 'distance_label' => 'Wellness', 'tickets' => [['id' => 'general', 'name' => 'General Entry', 'description' => 'Morning yoga session', 'price' => 95000, 'stock' => 25, 'capacity' => '25 left']]],
            ['community' => 'puncak-runners', 'id' => 'evt_007', 'slug' => 'puncak-pass-half-marathon', 'title' => 'Puncak Pass Half Marathon', 'category' => 'Trail Run', 'activity' => 'trail-run', 'starts_at' => '2026-05-11 06:00:00', 'ends_at' => '2026-05-11 10:00:00', 'date_label' => 'Sun, 11 May - 06:00', 'full_date_label' => 'Sunday, 11 May 2026', 'time_label' => 'Finished 08:14 WIB', 'location' => 'Puncak Pass, Cianjur', 'region' => 'West Java', 'price_label' => 'From Rp 200K', 'spots_label' => '480 finishers', 'cover_image' => 'puncak-pass-half-marathon.jpg', 'image_alt' => 'A running community gathered on a mountain road', 'distance_label' => '21K', 'recap_href' => '/events/puncak-pass-half-marathon/recap', 'tickets' => [['id' => '21k', 'name' => '21K', 'description' => 'Race entry', 'price' => 200000, 'stock' => 0, 'capacity' => '480 finishers']]],
            ['community' => 'puncak-runners', 'id' => 'evt_009', 'slug' => 'tea-valley-relay', 'title' => 'Tea Valley Relay', 'category' => 'Relay', 'activity' => 'trail-run', 'starts_at' => '2026-07-19 06:00:00', 'ends_at' => '2026-07-19 10:30:00', 'date_label' => 'Sun, 19 Jul - 06:00', 'full_date_label' => 'Sunday, 19 July 2026', 'time_label' => 'Relay starts 06:00 WIB', 'location' => 'Cisarua Tea Valley', 'region' => 'West Java', 'price_label' => 'From Rp 165K', 'spots_label' => '60 spots left', 'cover_image' => 'tea-valley-relay.jpg', 'image_alt' => 'Relay runners gathering in a highland tea valley', 'distance_label' => 'Relay', 'tickets' => [['id' => 'team', 'name' => 'Team Relay', 'description' => 'Relay registration per runner', 'price' => 165000, 'stock' => 60, 'capacity' => '60 left']]],
            ['community' => 'puncak-in', 'id' => 'evt_010', 'slug' => 'highland-campfire-night', 'title' => 'Highland Campfire Night', 'category' => 'Camping', 'activity' => 'camping', 'starts_at' => '2026-07-04 15:00:00', 'ends_at' => '2026-07-05 10:00:00', 'date_label' => 'Sat-Sun, 4-5 Jul', 'full_date_label' => 'Saturday-Sunday, 4-5 July 2026', 'time_label' => 'Check-in 15:00 WIB', 'location' => 'Ranca Upas, Ciwidey', 'region' => 'West Java', 'price_label' => 'From Rp 320K', 'spots_label' => '18 spots left', 'cover_image' => 'highland-campfire-night.jpg', 'image_alt' => 'Travellers gathering around a warm highland campfire', 'distance_label' => 'Campout', 'tickets' => [['id' => 'camp-pass', 'name' => 'Camp Pass', 'description' => 'Tent area, dinner, bonfire, and breakfast', 'price' => 320000, 'stock' => 18, 'capacity' => '18 left']]],
        ];

        foreach ($events as $eventData) {
            $community = $communities[$eventData['community']];
            $place = isset($eventData['place']) ? $places[$eventData['place']] : null;
            $coverImage = $this->copySeedAsset('events', $eventData['cover_image']);

            $event = Event::query()->updateOrCreate(
                ['slug' => $eventData['slug']],
                [
                    'community_id' => $community->id,
                    'place_id' => $place?->id,
                    'public_id' => $eventData['id'],
                    'title' => $eventData['title'],
                    'description' => $eventData['summary'][0] ?? $eventData['title'],
                    'category' => $eventData['category'],
                    'activity' => $eventData['activity'],
                    'activity_type' => $eventData['activity'],
                    'distance_label' => $eventData['distance_label'],
                    'elevation_label' => $eventData['elevation_label'] ?? null,
                    'difficulty' => $eventData['difficulty'] ?? 'Friendly pace',
                    'venue_name' => $eventData['venue_name'] ?? $eventData['location'],
                    'venue_description' => $eventData['venue_description'] ?? 'Full notes will be shared with registered participants.',
                    'summary' => $eventData['summary'] ?? null,
                    'includes' => $eventData['includes'] ?? null,
                    'schedule' => $eventData['schedule'] ?? null,
                    'starts_at' => Carbon::parse($eventData['starts_at'], 'Asia/Jakarta')->utc(),
                    'ends_at' => Carbon::parse($eventData['ends_at'], 'Asia/Jakarta')->utc(),
                    'publication_status' => Event::PUBLICATION_PUBLISHED,
                    'status_label' => null,
                    'date_label' => $eventData['date_label'],
                    'full_date_label' => $eventData['full_date_label'],
                    'time_label' => $eventData['time_label'],
                    'location' => $eventData['location'],
                    'region' => $eventData['region'],
                    'price_label' => $eventData['price_label'],
                    'spots_label' => $eventData['spots_label'],
                    'cover_image' => $coverImage,
                    'image_alt' => $eventData['image_alt'],
                    'detail_href' => "/events/{$eventData['slug']}",
                    'booking_href' => "/events/{$eventData['slug']}/booking",
                    'recap_href' => $eventData['recap_href'] ?? null,
                ]
            );

            foreach ($eventData['tickets'] as $ticket) {
                TicketType::query()->updateOrCreate(
                    ['event_id' => $event->id, 'public_id' => $ticket['id']],
                    [
                        'name' => $ticket['name'],
                        'description' => $ticket['description'],
                        'price' => $ticket['price'],
                        'currency' => 'IDR',
                        'quantity' => $ticket['stock'],
                        'sold' => 0,
                        'capacity_label' => $ticket['capacity'],
                    ]
                );
            }
        }

        Event::query()
            ->whereIn('slug', ['highland-camp-bonfire', 'lakeside-camp-weekend'])
            ->whereDoesntHave('bookings')
            ->get()
            ->each(function (Event $event): void {
                $event->ticketTypes()->delete();
                $event->delete();
            });

        $galleryItems = [
            ['summit-push', 'Summit push at dawn', 'Misty Ridge Hike', 'hike', '2026', 'summit-push-at-dawn.jpg', 'Hikers moving toward a summit at dawn'],
            ['pack-rolls-out', 'The 21K pack rolls out', 'Half Marathon', 'trail-run', '2026', 'pack-rolls-out.jpg', 'A pack of runners beginning a mountain race'],
            ['tea-switchbacks', 'Tea-plantation switchbacks', 'Trail Run 2026', 'trail-run', '2026', 'tea-plantation-switchbacks.jpg', 'Trail runners moving along highland switchbacks'],
            ['bonfire-stargazing', 'Bonfire & stargazing', 'Highland Camp', 'camping', '2026', 'bonfire-stargazing.jpg', 'Campers gathering near a warm highland bonfire'],
            ['cool-down', 'Cool-down at the falls', 'Forest Fun Run', 'trail-run', '2026', 'cool-down-waterfall.jpg', 'A forest trail scene used for a post-run cool-down moment'],
            ['sunrise-yoga', 'Sunrise mountain yoga', 'Mindful Mountain', 'wellness', '2026', 'sunrise-yoga.jpg', 'A calm sunrise mountain view for a wellness event'],
            ['walking-crew', 'Walking crew, all paces', 'Healthy Walk', 'walk', '2025', 'walking-crew.jpg', 'A walking community following a green highland path'],
            ['lakeside-morning', 'Lakeside camp morning', 'Situ Patenggang', 'camping', '2025', 'lakeside-camp-morning.jpg', 'Morning light over a highland camping area'],
            ['meadow-rest', 'Meadow rest stop', 'Papandayan Hike', 'hike', '2025', 'meadow-rest-stop.jpg', 'Friends resting in a highland meadow during a hike'],
        ];

        foreach ($galleryItems as $index => [$id, $title, $event, $category, $year, $imageFile, $alt]) {
            $sourcePath = base_path("database/seeders/assets/gallery/{$imageFile}");
            $storagePath = "gallery/demo/{$imageFile}";

            if (! is_file($sourcePath)) {
                throw new \RuntimeException("Missing seeded gallery asset: {$sourcePath}");
            }

            Storage::disk('public')->put($storagePath, file_get_contents($sourcePath));

            Gallery::query()->updateOrCreate(
                ['public_id' => $id],
                [
                    'community_id' => $communities['puncak-runners']->id,
                    'event_id' => null,
                    'title' => $title,
                    'event_label' => $event,
                    'category' => $category,
                    'year' => $year,
                    'image_path' => $storagePath,
                    'image_alt' => $alt,
                    'caption' => $title,
                    'created_at' => now()->subDays($index),
                ]
            );
        }

        foreach ([
            ['Email us', 'halo@puncaktravellers.id', 'For event questions, partnerships, and media.', 'mail'],
            ['WhatsApp', '+62 812 3456 7890', 'Fast help before race day or camp check-in.', 'phone'],
            ['Find us', 'Jl. Pajajaran No.12, Bogor, West Java', 'Our base for crew meetups and event briefings.', 'map'],
        ] as $index => [$title, $value, $description, $icon]) {
            ContactMethod::query()->updateOrCreate(
                ['title' => $title],
                [
                    'value' => $value,
                    'description' => $description,
                    'icon' => $icon,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $alex = User::query()->updateOrCreate(
            ['email' => 'alex@example.com'],
            [
                'name' => 'Alex Puncak',
                'password' => 'password',
                'location' => 'Bandung, ID',
                'crew' => 'Puncak Runners',
                'avatar' => null,
            ]
        );

        SavedEvent::query()->firstOrCreate([
            'user_id' => $alex->id,
            'event_id' => Event::query()->where('slug', 'misty-ridge-hike')->value('id'),
        ]);

        $demoBookings = [
            ['reference' => 'PTR-26-8F3K2A', 'event' => 'puncak-trail-run-2026', 'tickets' => ['21k' => 1, '5k' => 1]],
            ['reference' => 'PHM-26-7K2P0Q', 'event' => 'puncak-pass-half-marathon', 'tickets' => ['21k' => 1]],
        ];

        foreach ($demoBookings as $demoBooking) {
            $event = Event::query()->where('slug', $demoBooking['event'])->first();

            if ($event === null) {
                continue;
            }

            $booking = Booking::query()->updateOrCreate(
                ['reference' => $demoBooking['reference']],
                [
                    'user_id' => $alex->id,
                    'event_id' => $event->id,
                    'status' => $event->status === 'completed' ? Booking::STATUS_COMPLETED : Booking::STATUS_CONFIRMED,
                    'payment_status' => Booking::PAYMENT_PAID,
                    'attendee_name' => $alex->name,
                    'attendee_email' => $alex->email,
                    'subtotal' => 0,
                    'booking_fee' => 5000,
                    'total' => 0,
                    'currency' => 'IDR',
                    'idempotency_key' => 'seed-'.$demoBooking['reference'],
                ]
            );

            $subtotal = 0;

            foreach ($demoBooking['tickets'] as $ticketPublicId => $quantity) {
                $ticketType = TicketType::query()
                    ->whereBelongsTo($event)
                    ->where('public_id', $ticketPublicId)
                    ->first();

                if ($ticketType === null) {
                    continue;
                }

                BookingItem::query()->updateOrCreate(
                    ['booking_id' => $booking->id, 'ticket_type_id' => $ticketType->id],
                    [
                        'quantity' => $quantity,
                        'unit_price' => $ticketType->price,
                    ]
                );

                $subtotal += $quantity * $ticketType->price;
            }

            $booking->update([
                'subtotal' => $subtotal,
                'total' => $subtotal + $booking->booking_fee,
            ]);
        }
    }

    private function copySeedAsset(string $type, string $imageFile): string
    {
        $sourcePath = base_path("database/seeders/assets/{$type}/{$imageFile}");
        $storagePath = "{$type}/demo/{$imageFile}";

        if (! is_file($sourcePath)) {
            throw new \RuntimeException("Missing seeded {$type} asset: {$sourcePath}");
        }

        Storage::disk('public')->put($storagePath, file_get_contents($sourcePath));

        return $storagePath;
    }
}
