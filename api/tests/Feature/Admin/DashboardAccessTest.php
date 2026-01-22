<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        // GIVEN: An authenticated admin user
        $admin = User::factory()->create(['email' => 'admin@moviemind.local']);

        // WHEN: They access the admin dashboard
        $response = $this->actingAs($admin)->get('/admin');

        // THEN: They should see the dashboard
        $response->assertSuccessful();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // GIVEN: A guest user (not authenticated)

        // WHEN: They try to access the admin dashboard
        $response = $this->get('/admin');

        // THEN: They should be redirected to the login page
        $response->assertRedirect('/admin/login');
    }
}
