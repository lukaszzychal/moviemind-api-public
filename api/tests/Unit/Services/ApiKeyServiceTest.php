<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new ApiKeyService;
    }

    public function test_generate_key_returns_valid_format(): void
    {
        $key = $this->service->generateKey();

        $this->assertStringStartsWith('mm_', $key);
        $this->assertGreaterThan(40, strlen($key)); // mm_ + at least 40 chars
    }

    public function test_generate_key_returns_unique_keys(): void
    {
        $key1 = $this->service->generateKey();
        $key2 = $this->service->generateKey();

        $this->assertNotEquals($key1, $key2);
    }

    public function test_hash_key_returns_different_value(): void
    {
        // Use a clearly fake key for testing (not a real API key)
        $plaintext = 'mm_test_example_key_not_real_12345678901234567890';
        $hashed = $this->service->hashKey($plaintext);

        $this->assertNotEquals($plaintext, $hashed);
        $this->assertStringStartsWith('$2y$', $hashed); // bcrypt format
    }

    public function test_validate_key_returns_true_for_valid_key(): void
    {
        // Use a clearly fake key for testing (not a real API key)
        $plaintext = 'mm_test_example_key_not_real_12345678901234567890';
        $hashed = $this->service->hashKey($plaintext);

        $this->assertTrue($this->service->validateKey($plaintext, $hashed));
    }

    public function test_validate_key_returns_false_for_invalid_key(): void
    {
        // Use clearly fake keys for testing (not real API keys)
        $plaintext = 'mm_test_example_key_not_real_12345678901234567890';
        $wrongPlaintext = 'mm_wrong_example_key_not_real_12345678901234567890';
        $hashed = $this->service->hashKey($plaintext);

        $this->assertFalse($this->service->validateKey($wrongPlaintext, $hashed));
    }

    public function test_extract_prefix_returns_correct_prefix(): void
    {
        // Use a clearly fake key for testing (not a real API key)
        $key = 'mm_test_prefix_example_key_not_real_1234567890';
        $prefix = $this->service->extractPrefix($key);

        $this->assertEquals('test_pre', $prefix); // First 8 chars after "mm_"
    }

    public function test_create_key_returns_plaintext_and_model(): void
    {
        $result = $this->service->createKey('Test Key');

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('apiKey', $result);
        $this->assertStringStartsWith('mm_', $result['key']);
        $this->assertInstanceOf(ApiKey::class, $result['apiKey']);
        $this->assertEquals('Test Key', $result['apiKey']->name);
        $this->assertTrue($result['apiKey']->is_active);
    }

    public function test_create_key_stores_hashed_key(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];
        $apiKey = $result['apiKey'];

        // Key in database should be hashed (different from plaintext)
        $this->assertNotEquals($plaintext, $apiKey->key);
        $this->assertStringStartsWith('$2y$', $apiKey->key); // bcrypt format
    }

    public function test_find_key_by_plaintext_returns_correct_key(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];

        $found = $this->service->findKeyByPlaintext($plaintext);

        $this->assertNotNull($found);
        $this->assertEquals($result['apiKey']->id, $found->id);
    }

    public function test_find_key_by_plaintext_returns_null_for_invalid_key(): void
    {
        $this->service->createKey('Test Key');

        // Use a clearly fake key for testing (not a real API key)
        $found = $this->service->findKeyByPlaintext('mm_invalid_example_key_not_real_12345678901234567890');

        $this->assertNull($found);
    }

    public function test_get_key_plan_returns_plan_id(): void
    {
        // Create a plan first
        $plan = \App\Models\SubscriptionPlan::factory()->create();
        $planId = $plan->id;

        $result = $this->service->createKey('Test Key', planId: $planId);
        $plaintext = $result['key'];

        $foundPlanId = $this->service->getKeyPlan($plaintext);

        $this->assertEquals($planId, $foundPlanId);
    }

    public function test_track_usage_updates_last_used_at(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];
        $apiKey = $result['apiKey'];

        $this->assertNull($apiKey->last_used_at);

        $this->service->trackUsage($plaintext);

        $apiKey->refresh();
        $this->assertNotNull($apiKey->last_used_at);
    }

    public function test_validate_and_get_key_returns_key_for_valid_key(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];

        $found = $this->service->validateAndGetKey($plaintext);

        $this->assertNotNull($found);
        $this->assertEquals($result['apiKey']->id, $found->id);
    }

    public function test_validate_and_get_key_returns_null_for_inactive_key(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];
        $apiKey = $result['apiKey'];
        $apiKey->update(['is_active' => false]);

        $found = $this->service->validateAndGetKey($plaintext);

        $this->assertNull($found);
    }

    public function test_validate_and_get_key_returns_null_for_expired_key(): void
    {
        $result = $this->service->createKey('Test Key');
        $plaintext = $result['key'];
        $apiKey = $result['apiKey'];
        $apiKey->update(['expires_at' => now()->subDay()]);

        $found = $this->service->validateAndGetKey($plaintext);

        $this->assertNull($found);
    }
}
