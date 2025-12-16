<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HorizonBasicAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        // Create a test route protected by horizon.basic middleware
        Route::middleware(['web', 'horizon.basic'])->get('/test-horizon-auth', function () {
            return response()->json(['message' => 'Authorized']);
        });
    }

    public function test_basic_auth_bypasses_in_local_environment(): void
    {
        config(['app.env' => 'local']);
        config(['horizon.auth.bypass_environments' => ['local', 'staging']]);

        $response = $this->get('/test-horizon-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_basic_auth_bypasses_in_staging_environment(): void
    {
        config(['app.env' => 'staging']);
        config(['horizon.auth.bypass_environments' => ['local', 'staging']]);

        $response = $this->get('/test-horizon-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_basic_auth_requires_credentials_in_production(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->get('/test-horizon-auth');

        $response->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Horizon Dashboard"');
    }

    public function test_basic_auth_allows_access_with_valid_credentials(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->withBasicAuth('admin@example.com', 'test-password')
            ->get('/test-horizon-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_basic_auth_denies_access_with_invalid_email(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->withBasicAuth('unauthorized@example.com', 'test-password')
            ->get('/test-horizon-auth');

        $response->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Horizon Dashboard"');
    }

    public function test_basic_auth_denies_access_with_invalid_password(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->withBasicAuth('admin@example.com', 'wrong-password')
            ->get('/test-horizon-auth');

        $response->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Horizon Dashboard"');
    }

    public function test_basic_auth_email_comparison_is_case_insensitive(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['Admin@Example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->withBasicAuth('admin@example.com', 'test-password')
            ->get('/test-horizon-auth');

        $response->assertOk()
            ->assertJson(['message' => 'Authorized']);
    }

    public function test_basic_auth_handles_multiple_allowed_emails(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com', 'ops@example.com']]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response1 = $this->withBasicAuth('admin@example.com', 'test-password')
            ->get('/test-horizon-auth');
        $response1->assertOk();

        $response2 = $this->withBasicAuth('ops@example.com', 'test-password')
            ->get('/test-horizon-auth');
        $response2->assertOk();
    }

    public function test_basic_auth_denies_access_when_no_password_configured(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);
        config(['horizon.auth.basic_auth_password' => null]);

        $response = $this->withBasicAuth('admin@example.com', 'any-password')
            ->get('/test-horizon-auth');

        $response->assertStatus(401);
    }

    public function test_basic_auth_denies_access_when_no_emails_configured(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => []]);
        config(['horizon.auth.basic_auth_password' => 'test-password']);

        $response = $this->withBasicAuth('admin@example.com', 'test-password')
            ->get('/test-horizon-auth');

        $response->assertStatus(401);
    }
}
