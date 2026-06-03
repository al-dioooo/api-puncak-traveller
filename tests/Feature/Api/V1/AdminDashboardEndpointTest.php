<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_dashboard_returns_dynamic_summary_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $member = User::factory()->create();
        $event = Event::factory()->upcoming()->create(['title' => 'Dynamic Trail']);
        $ticket = TicketType::factory()->for($event)->create([
            'quantity' => 20,
            'sold' => 2,
            'price' => 100000,
        ]);
        $booking = Booking::factory()->for($member)->for($event)->create([
            'reference' => 'PTR-DYNAMIC',
            'payment_status' => Booking::PAYMENT_PAID,
            'total' => 205000,
        ]);
        $booking->items()->create([
            'ticket_type_id' => $ticket->id,
            'quantity' => 2,
            'unit_price' => 100000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson(route('api.v1.admin.dashboard'))
            ->assertOk()
            ->assertJsonPath('data.stats.eventsCount', 1)
            ->assertJsonPath('data.stats.upcomingEvents', 1)
            ->assertJsonPath('data.stats.bookingsCount', 1)
            ->assertJsonPath('data.stats.ticketsSold', 2)
            ->assertJsonPath('data.stats.revenue', 205000)
            ->assertJsonPath('data.recentBookings.0.reference', 'PTR-DYNAMIC')
            ->assertJsonPath('data.upcomingEvents.0.title', 'Dynamic Trail')
            ->assertJsonStructure([
                'data' => [
                    'chart' => ['labels', 'values'],
                ],
            ]);
    }

    public function test_member_cannot_access_admin_dashboard_summary(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.admin.dashboard'))
            ->assertForbidden();
    }
}
