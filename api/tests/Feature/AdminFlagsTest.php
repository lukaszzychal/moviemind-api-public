<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class AdminFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Bypass Admin API auth for tests (testing flag functionality, not auth)
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');
    }

    public function test_list_flags(): void
    {
        // GIVEN: Admin API is accessible (auth bypassed in setUp)

        // WHEN: Requesting list of flags
        $res = $this->getJson('/api/v1/admin/flags');

        // THEN: Should return OK with correct structure
        $res->assertOk()->assertJsonStructure(['data' => [['name', 'active', 'description', 'category', 'default', 'togglable']]]);
    }

    public function test_toggle_flag(): void
    {
        // GIVEN: Flag is deactivated
        Feature::deactivate('ai_description_generation');

        // WHEN: Toggling flag to 'on'
        $res = $this->postJson('/api/v1/admin/flags/ai_description_generation', ['state' => 'on']);

        // THEN: Should return OK with flag activated
        $res->assertOk()->assertJson(['name' => 'ai_description_generation', 'active' => true]);
    }

    public function test_toggle_flag_rejects_unknown_name(): void
    {
        // GIVEN: Unknown flag name

        // WHEN: Attempting to toggle unknown flag
        $res = $this->postJson('/api/v1/admin/flags/unknown-feature', ['state' => 'on']);

        // THEN: Should return 404
        $res->assertStatus(404);
    }

    public function test_toggle_flag_rejects_non_togglable_flag(): void
    {
        // GIVEN: Non-togglable flag exists

        // WHEN: Attempting to toggle non-togglable flag
        $res = $this->postJson('/api/v1/admin/flags/hallucination_guard', ['state' => 'off']);

        // THEN: Should return 403 Forbidden
        $res->assertStatus(403);
    }

    public function test_usage_endpoint(): void
    {
        // GIVEN: Flags are used in codebase

        // WHEN: Requesting flag usage information
        $res = $this->getJson('/api/v1/admin/flags/usage');

        // THEN: Should return OK with usage structure
        $res->assertOk()->assertJsonStructure(['usage' => [['file', 'line', 'pattern', 'name']]]);

        // THEN: GenerateController usage should include a flag name
        $entries = collect($res->json('usage'))
            ->where('file', 'app/Http/Controllers/Api/GenerateController.php')
            ->values();
        if ($entries->isNotEmpty()) {
            $this->assertNotEmpty($entries[0]['name'] ?? null);
        }
    }

    public function test_tmdb_verification_flag_is_togglable(): void
    {
        // GIVEN: Flag is deactivated
        Feature::deactivate('tmdb_verification');

        // WHEN: Toggling flag to 'on'
        $res = $this->postJson('/api/v1/admin/flags/tmdb_verification', ['state' => 'on']);

        // THEN: Should return OK with flag activated
        $res->assertOk()->assertJson(['name' => 'tmdb_verification', 'active' => true]);

        // WHEN: Toggling flag to 'off'
        $res = $this->postJson('/api/v1/admin/flags/tmdb_verification', ['state' => 'off']);

        // THEN: Should return OK with flag deactivated
        $res->assertOk()->assertJson(['name' => 'tmdb_verification', 'active' => false]);
    }

    public function test_tmdb_verification_flag_is_listed(): void
    {
        // GIVEN: Flags exist in system

        // WHEN: Requesting list of flags
        $res = $this->getJson('/api/v1/admin/flags');
        $res->assertOk();

        // THEN: tmdb_verification flag should be in the list with correct properties
        $tmdbFlag = collect($res->json('data'))->firstWhere('name', 'tmdb_verification');
        $this->assertNotNull($tmdbFlag);
        $this->assertSame('tmdb_verification', $tmdbFlag['name']);
        $this->assertTrue($tmdbFlag['togglable']);
        $this->assertSame('moderation', $tmdbFlag['category']);
    }
}
