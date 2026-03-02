<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\WebhookSubscription;
use App\Services\WebhookSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebhookSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookSubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = app(WebhookSubscriptionService::class);
    }

    public function test_list_registered_returns_env_urls_with_source_env(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => ['https://env1.example.com'],
            'movie.generation.failed' => [],
        ]);

        $list = $this->service->listRegistered();

        $envEntries = array_filter($list, fn (array $e) => $e['source'] === 'env');
        $this->assertCount(1, $envEntries);
        $first = array_values($envEntries)[0];
        $this->assertSame('movie.generation.completed', $first['event_type']);
        $this->assertSame('https://env1.example.com', $first['url']);
        $this->assertSame('env', $first['source']);
        $this->assertArrayNotHasKey('id', $first);
    }

    public function test_list_registered_returns_subscription_urls_with_source_and_id(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [],
        ]);
        $sub = WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://sub1.example.com',
        ]);

        $list = $this->service->listRegistered();

        $subEntries = array_filter($list, fn (array $e) => $e['source'] === 'subscription');
        $this->assertCount(1, $subEntries);
        $first = array_values($subEntries)[0];
        $this->assertSame('movie.generation.completed', $first['event_type']);
        $this->assertSame('https://sub1.example.com', $first['url']);
        $this->assertSame('subscription', $first['source']);
        $this->assertSame($sub->id, $first['id']);
    }

    public function test_list_registered_merges_env_and_subscription_for_same_event_type(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => ['https://env.example.com'],
        ]);
        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://sub.example.com',
        ]);

        $list = $this->service->listRegistered();

        $this->assertCount(2, $list);
        $sources = array_column($list, 'source');
        $this->assertContains('env', $sources);
        $this->assertContains('subscription', $sources);
    }

    public function test_add_subscription_creates_and_returns_model(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [],
        ]);

        $sub = $this->service->addSubscription('movie.generation.completed', 'https://new.example.com');

        $this->assertInstanceOf(WebhookSubscription::class, $sub);
        $this->assertSame('movie.generation.completed', $sub->event_type);
        $this->assertSame('https://new.example.com', $sub->url);
        $this->assertDatabaseHas('webhook_subscriptions', [
            'event_type' => 'movie.generation.completed',
            'url' => 'https://new.example.com',
        ]);
    }

    public function test_add_subscription_throws_for_invalid_event_type(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [],
        ]);

        $this->expectException(ValidationException::class);

        $this->service->addSubscription('invalid.event', 'https://example.com');
    }

    public function test_add_subscription_throws_for_duplicate_event_type_and_url(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [],
        ]);
        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://dup.example.com',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->addSubscription('movie.generation.completed', 'https://dup.example.com');
    }

    public function test_update_subscription_updates_and_returns_model(): void
    {
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [],
            'movie.generation.failed' => [],
        ]);
        $sub = WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://old.example.com',
        ]);

        $updated = $this->service->updateSubscription($sub->id, 'movie.generation.failed', 'https://new.example.com');

        $this->assertSame('movie.generation.failed', $updated->event_type);
        $this->assertSame('https://new.example.com', $updated->url);
        $this->assertDatabaseHas('webhook_subscriptions', [
            'id' => $sub->id,
            'event_type' => 'movie.generation.failed',
            'url' => 'https://new.example.com',
        ]);
    }

    public function test_update_subscription_throws_for_unknown_id(): void
    {
        Config::set('webhooks.outgoing_urls', ['movie.generation.completed' => []]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->updateSubscription('00000000-0000-0000-0000-000000000000', 'movie.generation.completed', 'https://example.com');
    }

    public function test_delete_subscription_removes_record(): void
    {
        Config::set('webhooks.outgoing_urls', ['movie.generation.completed' => []]);
        $sub = WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => 'https://del.example.com',
        ]);

        $this->service->deleteSubscription($sub->id);

        $this->assertDatabaseMissing('webhook_subscriptions', ['id' => $sub->id]);
    }

    public function test_delete_subscription_throws_for_unknown_id(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->deleteSubscription('00000000-0000-0000-0000-000000000000');
    }
}
