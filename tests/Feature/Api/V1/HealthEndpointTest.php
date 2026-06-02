<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_service_status(): void
    {
        $response = $this->getJson(route('api.v1.health'));

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.service', config('app.name'));
    }
}
