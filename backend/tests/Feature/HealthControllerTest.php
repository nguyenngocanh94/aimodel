<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_health_endpoint_returns_ok_status(): void
    {
        $response = $this->getJson('/api/health');

        // Response should be 200 (all healthy) or 503 (some services down)
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
                'redis',
                'queue',
            ],
        ]);

        $status = $response->json('status');
        $this->assertContains($status, ['ok', 'degraded']);

        $this->assertIsString($response->json('timestamp'));
        $this->assertNotEmpty($response->json('timestamp'));
    }

    public function test_health_endpoint_returns_service_statuses(): void
    {
        $response = $this->getJson('/api/health');

        $services = $response->json('services');

        $this->assertArrayHasKey('database', $services);
        $this->assertArrayHasKey('redis', $services);
        $this->assertArrayHasKey('queue', $services);

        $this->assertContains($services['database'], ['ok', 'error']);
        $this->assertContains($services['redis'], ['ok', 'error']);
        $this->assertContains($services['queue'], ['ok', 'error']);
    }

    public function test_health_endpoint_returns_iso8601_timestamp(): void
    {
        $response = $this->getJson('/api/health');

        $timestamp = $response->json('timestamp');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $timestamp
        );
    }
}
