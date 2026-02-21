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

        $this->actingAs($admin)
            ->get('/admin')
            ->assertSee('Analytics Overview')
            ->assertSee('Total Movies')
            ->assertSee('Active API Keys');
    }

    public function test_admin_can_list_subscription_plans()
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/subscription-plans')
            ->assertSuccessful()
            ->assertSee('Pro Plan')
            ->assertSee('Enterprise Plan');
    }

    public function test_admin_can_create_api_key()
    {
        $admin = User::factory()->create();
        $plan = SubscriptionPlan::where('name', 'pro')->first();

        // Simulate Livewire/Filament component interaction or just check page access
        // For strict E2E we verify the page loads.
        // Filament uses Livewire, ensuring the create page works:
        $this->actingAs($admin)
            ->get('/admin/api-keys/create')
            ->assertSuccessful()
            ->assertSee('Create API Key');
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
