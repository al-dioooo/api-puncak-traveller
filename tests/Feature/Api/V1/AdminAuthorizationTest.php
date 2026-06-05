<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    public function test_member_cannot_create_admin_event(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_MEMBER]));

        $this->postJson(route('api.v1.events.store'), $this->eventPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Administrator access required.');
    }

    public function test_admin_can_create_event(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->postJson(route('api.v1.events.store'), $this->eventPayload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'misty-ridge-hike-admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(): array
    {
        return [
            'title' => 'Misty Ridge Hike Admin',
            'slug' => 'misty-ridge-hike-admin',
            'category' => 'Hike',
            'activity' => 'hike',
            'description' => 'A guided admin-created event.',
            'starts_at' => now()->addMonth()->toIso8601String(),
            'ends_at' => now()->addMonth()->addHours(4)->toIso8601String(),
            'location' => 'Kebun Raya Cibodas',
            'status' => 'draft',
            'tickets' => [
                ['name' => 'General Registration', 'price' => 150000, 'stock' => 100],
            ],
        ];
    }
}
