<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Config::set('admin.api_token', $this->token);
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => ['https://env.example.com'],
            'movie.generation.failed' => [],
        ]);

        // Bypass Admin API auth for tests (so requests without token also work in CI)
        config(['app.env' => 'testing']);
        config(['admin.auth.bypass_environments' => ['local', 'staging', 'testing']]);
    }

    public function test_list_returns_combined_urls_with_source(): void
    {
        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://sub.example.com',
        ]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson('/api/v1/admin/webhook-subscriptions');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['event_type', 'url', 'source']]]);
        $data = $response->json('data');
        $envEntries = array_filter($data, fn (array $e) => $e['source'] === 'env');
        $subEntries = array_filter($data, fn (array $e) => $e['source'] === 'subscription');
        $this->assertCount(1, $envEntries);
        $this->assertCount(1, $subEntries);
        $this->assertSame('https://env.example.com', array_values($envEntries)[0]['url']);
        $this->assertSame('https://sub.example.com', array_values($subEntries)[0]['url']);
        $this->assertArrayHasKey('id', array_values($subEntries)[0]);
    }

    public function test_list_requires_admin_token(): void
    {
        // Disable bypass so endpoint requires token
        config(['app.env' => 'production']);
        config(['admin.auth.bypass_environments' => []]);

        $response = $this->getJson('/api/v1/admin/webhook-subscriptions');
        $response->assertUnauthorized();
    }

    public function test_post_creates_subscription_and_returns_201(): void
    {
        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson('/api/v1/admin/webhook-subscriptions', [
                'event_type' => 'movie.generation.completed',
                'url' => 'https://new.example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('event_type', 'movie.generation.completed')
            ->assertJsonPath('url', 'https://new.example.com')
            ->assertJsonStructure(['id', 'created_at']);
        $this->assertDatabaseHas('webhook_subscriptions', [
            'event_type' => 'movie.generation.completed',
            'url' => 'https://new.example.com',
        ]);
    }

    public function test_post_validates_event_type(): void
    {
        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson('/api/v1/admin/webhook-subscriptions', [
                'event_type' => 'invalid.event',
                'url' => 'https://example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);
    }

    public function test_post_validates_url(): void
    {
        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson('/api/v1/admin/webhook-subscriptions', [
                'event_type' => 'movie.generation.completed',
                'url' => 'not-a-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_post_returns_422_for_duplicate(): void
    {
        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://dup.example.com',
        ]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson('/api/v1/admin/webhook-subscriptions', [
                'event_type' => 'movie.generation.completed',
                'url' => 'https://dup.example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_patch_updates_subscription(): void
    {
        $sub = WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://old.example.com',
        ]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->patchJson("/api/v1/admin/webhook-subscriptions/{$sub->id}", [
                'event_type' => 'movie.generation.failed',
                'url' => 'https://new.example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('event_type', 'movie.generation.failed')
            ->assertJsonPath('url', 'https://new.example.com');
        $this->assertDatabaseHas('webhook_subscriptions', [
            'id' => $sub->id,
            'event_type' => 'movie.generation.failed',
            'url' => 'https://new.example.com',
        ]);
    }

    public function test_patch_returns_404_for_unknown_id(): void
    {
        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->patchJson('/api/v1/admin/webhook-subscriptions/00000000-0000-0000-0000-000000000000', [
                'event_type' => 'movie.generation.completed',
                'url' => 'https://example.com',
            ]);

        $response->assertStatus(404);
    }

    public function test_delete_removes_subscription_returns_204(): void
    {
        $sub = WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://del.example.com',
        ]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->deleteJson("/api/v1/admin/webhook-subscriptions/{$sub->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('webhook_subscriptions', ['id' => $sub->id]);
    }

    public function test_delete_returns_404_for_unknown_id(): void
    {
        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->deleteJson('/api/v1/admin/webhook-subscriptions/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
