<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_root_returns_service_metadata(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', 'v1');
    }

    public function test_api_health_returns_application_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'database', 'timestamp']);
    }

    public function test_framework_health_endpoint_is_still_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
