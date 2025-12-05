<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response = $this->get('/debug');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'version',
                'status',
                'environment',
                'ai_service',
                'feature_flags' => [
                    'active',
                ],
                'endpoints',
                'documentation',
            ]);
    }

    public function test_root_endpoint_returns_welcome_view(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewIs('welcome');
    }

    public function test_debug_endpoint_includes_ai_service(): void
    {
        $response = $this->getJson('/debug');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('ai_service', $data);
        $this->assertContains($data['ai_service'], ['mock', 'real']);
    }

    public function test_debug_endpoint_includes_active_feature_flags(): void
    {
        $response = $this->getJson('/debug');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('feature_flags', $data);
        $this->assertArrayHasKey('active', $data['feature_flags']);
        $this->assertIsArray($data['feature_flags']['active']);

        // Check structure of active flags
        if (! empty($data['feature_flags']['active'])) {
            $firstFlag = $data['feature_flags']['active'][0];
            $this->assertArrayHasKey('name', $firstFlag);
        }
    }
}
