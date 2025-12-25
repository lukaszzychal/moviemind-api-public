<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Bypass Admin API auth for tests (testing API key management functionality, not auth)
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');

        $this->apiKeyService = new ApiKeyService;
    }

    public function test_list_api_keys(): void
    {
        $this->apiKeyService->createKey('Key 1');
        $this->apiKeyService->createKey('Key 2');

        $response = $this->getJson('/api/v1/admin/api-keys');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'key_prefix', 'plan_id', 'user_id', 'is_active', 'last_used_at', 'expires_at', 'created_at'],
                ],
                'count',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_list_api_keys_filters_by_active_status(): void
    {
        $result1 = $this->apiKeyService->createKey('Active Key');
        $result2 = $this->apiKeyService->createKey('Inactive Key');
        $result2['apiKey']->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/admin/api-keys?active=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Key');
    }

    public function test_list_api_keys_filters_by_plan_id(): void
    {
        // Create a plan first
        $plan = \App\Models\SubscriptionPlan::factory()->create();
        $planId = $plan->id;

        $this->apiKeyService->createKey('Key with Plan', planId: $planId);
        $this->apiKeyService->createKey('Key without Plan');

        $response = $this->getJson("/api/v1/admin/api-keys?plan_id={$planId}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plan_id', $planId);
    }

    public function test_create_api_key(): void
    {
        $response = $this->postJson('/api/v1/admin/api-keys', [
            'name' => 'Test API Key',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'key',
                'key_prefix',
                'plan_id',
                'is_active',
                'created_at',
                'warning',
            ])
            ->assertJson([
                'name' => 'Test API Key',
                'is_active' => true,
            ])
            ->assertJsonPath('warning', 'Save this key immediately. You will not be able to see it again.');

        // Verify key starts with mm_
        $this->assertStringStartsWith('mm_', $response->json('key'));

        // Verify key was stored in database (without plaintext)
        $apiKey = ApiKey::find($response->json('id'));
        $this->assertNotNull($apiKey);
        $this->assertNotEquals($response->json('key'), $apiKey->key); // Should be hashed
    }

    public function test_create_api_key_with_plan_id(): void
    {
        // Create a plan first
        $plan = \App\Models\SubscriptionPlan::factory()->create();
        $planId = $plan->id;

        $response = $this->postJson('/api/v1/admin/api-keys', [
            'name' => 'Test API Key',
            'plan_id' => $planId,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('plan_id', $planId)
            ->assertJsonStructure([
                'id',
                'name',
                'key',
                'key_prefix',
                'plan_id',
                'is_active',
                'created_at',
            ]);
    }

    public function test_create_api_key_requires_name(): void
    {
        $response = $this->postJson('/api/v1/admin/api-keys', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_revoke_api_key(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKeyId = $result['apiKey']->id;

        $response = $this->postJson("/api/v1/admin/api-keys/{$apiKeyId}/revoke");

        $response->assertOk()
            ->assertJson([
                'id' => $apiKeyId,
                'name' => 'Test Key',
                'is_active' => false,
                'message' => 'API key revoked successfully.',
            ]);

        // Verify key was deactivated
        $apiKey = ApiKey::find($apiKeyId);
        $this->assertFalse($apiKey->is_active);
    }

    public function test_revoke_api_key_returns_404_for_invalid_id(): void
    {
        $response = $this->postJson('/api/v1/admin/api-keys/non-existent-id/revoke');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not found',
                'message' => 'API key not found.',
            ]);
    }

    public function test_regenerate_api_key(): void
    {
        // Create a plan first
        $plan = \App\Models\SubscriptionPlan::factory()->create();
        $planId = $plan->id;

        $result = $this->apiKeyService->createKey('Test Key', planId: $planId);
        $oldApiKeyId = $result['apiKey']->id;

        $response = $this->postJson("/api/v1/admin/api-keys/{$oldApiKeyId}/regenerate", [
            'name' => 'Updated Key Name',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'key',
                'key_prefix',
                'plan_id',
                'is_active',
                'created_at',
                'revoked_key_id',
                'warning',
            ])
            ->assertJson([
                'name' => 'Updated Key Name',
                'revoked_key_id' => $oldApiKeyId,
            ])
            ->assertJsonPath('warning', 'Save this key immediately. You will not be able to see it again.');

        // Verify old key was revoked
        $oldApiKey = ApiKey::find($oldApiKeyId);
        $this->assertFalse($oldApiKey->is_active);

        // Verify new key was created
        $newApiKeyId = $response->json('id');
        $newApiKey = ApiKey::find($newApiKeyId);
        $this->assertNotNull($newApiKey);
        $this->assertTrue($newApiKey->is_active);
        $this->assertEquals($planId, $newApiKey->plan_id);
    }

    public function test_regenerate_api_key_returns_404_for_invalid_id(): void
    {
        $response = $this->postJson('/api/v1/admin/api-keys/non-existent-id/regenerate');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Not found',
                'message' => 'API key not found.',
            ]);
    }

    public function test_regenerate_api_key_keeps_original_name_if_not_provided(): void
    {
        $result = $this->apiKeyService->createKey('Original Name');
        $oldApiKeyId = $result['apiKey']->id;

        $response = $this->postJson("/api/v1/admin/api-keys/{$oldApiKeyId}/regenerate");

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Original Name');
    }
}
