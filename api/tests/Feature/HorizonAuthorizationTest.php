<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_horizon_allows_access_in_local_environment(): void
    {
        config(['app.env' => 'local']);
        config(['horizon.auth.bypass_environments' => ['local', 'staging']]);
        config(['horizon.auth.allowed_emails' => []]);

        $this->assertTrue(Gate::allows('viewHorizon'));
    }

    public function test_horizon_allows_access_in_staging_environment(): void
    {
        config(['app.env' => 'staging']);
        config(['horizon.auth.bypass_environments' => ['local', 'staging']]);
        config(['horizon.auth.allowed_emails' => []]);

        $this->assertTrue(Gate::allows('viewHorizon'));
    }

    public function test_horizon_denies_access_in_production_without_allowed_emails(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => []]);

        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    public function test_horizon_allows_access_in_production_with_authorized_email(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);

        $user = new class
        {
            public string $email = 'admin@example.com';
        };

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_horizon_denies_access_in_production_with_unauthorized_email(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);

        $user = new class
        {
            public string $email = 'unauthorized@example.com';
        };

        $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_horizon_denies_access_in_production_without_user(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);

        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    public function test_horizon_email_comparison_is_case_insensitive(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['Admin@Example.com']]);

        $user = new class
        {
            public string $email = 'admin@example.com';
        };

        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_horizon_handles_multiple_allowed_emails(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com', 'dev@example.com']]);

        $user1 = new class
        {
            public string $email = 'admin@example.com';
        };

        $user2 = new class
        {
            public string $email = 'dev@example.com';
        };

        $this->assertTrue(Gate::forUser($user1)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($user2)->allows('viewHorizon'));
    }

    public function test_horizon_prevents_bypass_in_production_even_if_configured(): void
    {
        // This test ensures that even if someone accidentally sets bypass_environments
        // to include 'production', it should still require authentication
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => ['local', 'staging', 'production']]);
        config(['horizon.auth.allowed_emails' => []]);

        // Security safeguard: Production should NEVER bypass authorization
        // Even if accidentally configured, we enforce authentication in production
        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    public function test_horizon_requires_authorized_emails_in_production(): void
    {
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => []]);
        config(['horizon.auth.allowed_emails' => []]);

        // Production MUST have authorized emails configured
        $this->assertFalse(Gate::allows('viewHorizon'));
    }

    public function test_horizon_allows_access_in_production_with_authorized_email_even_if_bypass_configured(): void
    {
        // Test that even if production is accidentally in bypass_environments,
        // it still requires authorized email
        config(['app.env' => 'production']);
        config(['horizon.auth.bypass_environments' => ['production']]);
        config(['horizon.auth.allowed_emails' => ['admin@example.com']]);

        $user = new class
        {
            public string $email = 'admin@example.com';
        };

        // Should require authentication even if production is in bypass
        // (the safeguard removes production from bypass list)
        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }
}
