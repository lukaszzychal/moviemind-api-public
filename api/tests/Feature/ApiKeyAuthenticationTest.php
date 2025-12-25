<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->apiKeyService = new ApiKeyService;

        // Create a test route protected by rapidapi.auth middleware
        Route::middleware(['rapidapi.auth'])->get('/test-rapidapi-auth', function () {
            return response()->json(['message' => 'Authorized']);
        });
    }

    public function test_middleware_requires_api_key(): void
    {
        $response = $this->getJson('/test-rapidapi-auth');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
            ])
            ->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_middleware_accepts_x_rapidapi_key_header(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];

        $response = $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey,
        ])->getJson('/test-rapidapi-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_middleware_accepts_authorization_bearer_header(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$plaintextKey}",
        ])->getJson('/test-rapidapi-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_middleware_rejects_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            // Use a clearly fake key for testing (not a real API key)
            'X-RapidAPI-Key' => 'mm_invalid_example_key_not_real_12345678901234567890',
        ])->getJson('/test-rapidapi-auth');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired API key',
            ]);
    }

    public function test_middleware_rejects_inactive_api_key(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];
        $apiKey = $result['apiKey'];
        $apiKey->update(['is_active' => false]);

        $response = $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey,
        ])->getJson('/test-rapidapi-auth');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired API key',
            ]);
    }

    public function test_middleware_rejects_expired_api_key(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];
        $apiKey = $result['apiKey'];
        $apiKey->update(['expires_at' => now()->subDay()]);

        $response = $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey,
        ])->getJson('/test-rapidapi-auth');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired API key',
            ]);
    }

    public function test_middleware_tracks_api_key_usage(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];
        $apiKey = $result['apiKey'];

        $this->assertNull($apiKey->last_used_at);

        $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey,
        ])->getJson('/test-rapidapi-auth');

        $apiKey->refresh();
        $this->assertNotNull($apiKey->last_used_at);
    }

    public function test_middleware_adds_api_key_to_request_attributes(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $plaintextKey = $result['key'];
        $apiKey = $result['apiKey'];

        // Create a route that checks request attributes
        Route::middleware(['rapidapi.auth'])->get('/test-rapidapi-attributes', function () {
            $apiKeyId = request()->attributes->get('api_key_id');
            $apiKeyModel = request()->attributes->get('api_key');

            return response()->json([
                'api_key_id' => $apiKeyId,
                'api_key_name' => $apiKeyModel?->name,
            ]);
        });

        $response = $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey,
        ])->getJson('/test-rapidapi-attributes');

        $response->assertOk()
            ->assertJson([
                'api_key_id' => $apiKey->id,
                'api_key_name' => 'Test Key',
            ]);
    }

    public function test_middleware_prioritizes_x_rapidapi_key_over_authorization(): void
    {
        $result1 = $this->apiKeyService->createKey('Key 1');
        $result2 = $this->apiKeyService->createKey('Key 2');
        $plaintextKey1 = $result1['key'];
        $plaintextKey2 = $result2['key'];

        // X-RapidAPI-Key should take priority over Authorization header
        $response = $this->withHeaders([
            'X-RapidAPI-Key' => $plaintextKey1,
            'Authorization' => "Bearer {$plaintextKey2}",
        ])->getJson('/test-rapidapi-auth');

        $response->assertOk();

        // Verify it used Key 1 (from X-RapidAPI-Key)
        $apiKey1 = $result1['apiKey'];
        $apiKey1->refresh();
        $this->assertNotNull($apiKey1->last_used_at);
    }
}
