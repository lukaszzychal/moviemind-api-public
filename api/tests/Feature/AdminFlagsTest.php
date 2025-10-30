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
        $res->assertOk()->assertJsonStructure(['data' => [['name','active','description']]]);
    }

    public function test_toggle_flag(): void
    {
        Feature::deactivate('ai_description_generation');
        $res = $this->postJson('/api/v1/admin/flags/ai_description_generation', ['state' => 'on']);
        $res->assertOk()->assertJson(['name' => 'ai_description_generation', 'active' => true]);
    }

    public function test_usage_endpoint(): void
    {
        $res = $this->getJson('/api/v1/admin/flags/usage');
        $res->assertOk()->assertJsonStructure(['usage']);
    }
}


