<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMemberEndpointTest extends TestCase
{
    public function test_admin_can_list_members_with_booking_stats(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'created_at' => now()->subDay(),
        ]);
        $member = User::factory()->create([
            'name' => 'Alex Puncak',
            'email' => 'alex@example.com',
            'created_at' => now(),
        ]);
        Booking::factory()->for($member)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('api.v1.members.index'));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.email', 'alex@example.com')
            ->assertJsonPath('data.0.stats.eventsBooked', 1);
    }

    public function test_member_cannot_list_members(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_MEMBER]));

        $this->getJson(route('api.v1.members.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Administrator access required.');
    }
}
