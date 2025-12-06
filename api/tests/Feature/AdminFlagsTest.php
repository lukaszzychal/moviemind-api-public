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
    }

    public function test_list_flags(): void
    {
        $res = $this->getJson('/api/v1/admin/flags');
        $res->assertOk()->assertJsonStructure(['data' => [['name', 'active', 'description', 'category', 'default', 'togglable']]]);
    }

    public function test_toggle_flag(): void
    {
        Feature::deactivate('ai_description_generation');
        $res = $this->postJson('/api/v1/admin/flags/ai_description_generation', ['state' => 'on']);
        $res->assertOk()->assertJson(['name' => 'ai_description_generation', 'active' => true]);
    }

    public function test_toggle_flag_rejects_unknown_name(): void
    {
        $res = $this->postJson('/api/v1/admin/flags/unknown-feature', ['state' => 'on']);
        $res->assertStatus(404);
    }

    public function test_toggle_flag_rejects_non_togglable_flag(): void
    {
        $res = $this->postJson('/api/v1/admin/flags/hallucination_guard', ['state' => 'off']);
        $res->assertStatus(403);
    }

    public function test_usage_endpoint(): void
    {
        $res = $this->getJson('/api/v1/admin/flags/usage');
        $res->assertOk()->assertJsonStructure(['usage' => [['file', 'line', 'pattern', 'name']]]);

        // Ensure GenerateController usage includes a flag name
        $entries = collect($res->json('usage'))
            ->where('file', 'app/Http/Controllers/Api/GenerateController.php')
            ->values();
        if ($entries->isNotEmpty()) {
            $this->assertNotEmpty($entries[0]['name'] ?? null);
        }
    }

    public function test_tmdb_verification_flag_is_togglable(): void
    {
        Feature::deactivate('tmdb_verification');
        $res = $this->postJson('/api/v1/admin/flags/tmdb_verification', ['state' => 'on']);
        $res->assertOk()->assertJson(['name' => 'tmdb_verification', 'active' => true]);

        $res = $this->postJson('/api/v1/admin/flags/tmdb_verification', ['state' => 'off']);
        $res->assertOk()->assertJson(['name' => 'tmdb_verification', 'active' => false]);
    }

    public function test_tmdb_verification_flag_is_listed(): void
    {
        $res = $this->getJson('/api/v1/admin/flags');
        $res->assertOk();

        $tmdbFlag = collect($res->json('data'))->firstWhere('name', 'tmdb_verification');
        $this->assertNotNull($tmdbFlag);
        $this->assertSame('tmdb_verification', $tmdbFlag['name']);
        $this->assertTrue($tmdbFlag['togglable']);
        $this->assertSame('moderation', $tmdbFlag['category']);
    }
}
