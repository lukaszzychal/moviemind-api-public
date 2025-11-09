<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_health_check_reports_success(): void
    {
        Config::set('services.openai.api_key', 'test-key');

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => []], 200, [
                'X-RateLimit-Remaining-Requests' => '2',
                'X-RateLimit-Remaining-Tokens' => '5000',
            ]),
        ]);

        $response = $this->getJson('/api/v1/health/openai');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'OpenAI API reachable',
                'status' => 200,
            ])
            ->assertJsonStructure([
                'rate_limit' => [
                    'requests_remaining',
                    'tokens_remaining',
                ],
            ]);
    }

    public function test_health_check_reports_error_without_key(): void
    {
        Config::set('services.openai.api_key', null);

        $response = $this->getJson('/api/v1/health/openai');

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => 'OpenAI API key not configured. Set OPENAI_API_KEY in .env',
            ]);
    }
}
