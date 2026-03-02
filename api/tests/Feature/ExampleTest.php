<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'status',
                'version',
                'api',
            ]);
    }

    public function test_root_endpoint_returns_welcome_json(): void
    {
        $response = $this->getJson('/');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'status',
                'version',
                'api',
            ]);

        $data = $response->json();
        $this->assertSame('Welcome to MovieMind API', $data['message']);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('1.0.0', $data['version']);
        $this->assertSame('/api/v1', $data['api']);
    }

    public function test_debug_endpoint_includes_ai_service(): void
    {
        Feature::activate('debug_endpoints');

        $response = $this->getJson('/api/v1/admin/debug/config');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('is_mock', $data['environment']);
        $this->assertArrayHasKey('is_real', $data['environment']);
        $this->assertTrue(
            $data['environment']['is_mock'] === true || $data['environment']['is_real'] === true,
            'AI service should be mock or real'
        );
    }

    public function test_debug_endpoint_includes_active_feature_flags(): void
    {
        Feature::activate('debug_endpoints');

        $response = $this->getJson('/api/v1/admin/debug/config');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertIsArray($data['endpoints']);
    }
}
