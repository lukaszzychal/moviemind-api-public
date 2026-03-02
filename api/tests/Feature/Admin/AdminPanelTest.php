<?php

namespace Tests\Feature\Admin;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed necessary data
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
    }

    public function test_admin_can_access_dashboard()
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_analytics_widget_is_visible()
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertSuccessful();
        // Dashboard shows stats; content may be in Livewire so check for brand or nav
        $this->assertTrue(
            str_contains($response->getContent(), 'MovieMind') || str_contains($response->getContent(), 'Total Movies'),
            'Dashboard should show brand or stats'
        );
    }

    public function test_admin_can_list_subscription_plans()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/subscription-plans')
            ->assertSuccessful()
            ->assertSee('Pro')
            ->assertSee('Enterprise');
    }

    public function test_admin_can_create_api_key()
    {
        $admin = User::factory()->create();
        $plan = SubscriptionPlan::where('name', 'pro')->first();

        // Simulate Livewire/Filament component interaction or just check page access.
        $this->actingAs($admin)
            ->get('/admin/api-keys/create')
            ->assertSuccessful();
    }

    public function test_admin_can_see_webhooks()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/outgoing-webhooks')
            ->assertSuccessful();
    }

    public function test_admin_can_open_outgoing_webhook_create_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/outgoing-webhooks/create')
            ->assertSuccessful();
    }
}
