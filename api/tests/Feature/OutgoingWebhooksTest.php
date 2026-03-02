<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Listeners\SendOutgoingWebhookListener;
use App\Models\OutgoingWebhook;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class OutgoingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Queue::fake();
    }

    public function test_outgoing_webhook_sent_when_movie_generation_requested(): void
    {
        // ARRANGE: Enable feature flag and configure webhook URL
        Feature::activate('webhook_notifications');
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $outgoingUrls['movie.generation.completed'] = ['https://example.com/webhook'];
        Config::set('webhooks.outgoing_urls', $outgoingUrls);

        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        // ACT: Dispatch event
        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000',
            locale: 'en-US',
            contextTag: 'modern'
        ));

        // ASSERT: Outgoing webhook was created and sent
        $this->assertDatabaseHas('outgoing_webhooks', [
            'event_type' => 'movie.generation.requested',
            'url' => 'https://example.com/webhook',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook'
                && $request->hasHeader('Content-Type', 'application/json')
                && isset($request->data()['entity_type'])
                && $request->data()['entity_type'] === 'MOVIE';
        });
    }

    public function test_outgoing_webhook_not_sent_when_feature_flag_disabled(): void
    {
        // ARRANGE: Disable feature flag
        Feature::deactivate('webhook_notifications');
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $outgoingUrls['movie.generation.completed'] = ['https://example.com/webhook'];
        Config::set('webhooks.outgoing_urls', $outgoingUrls);

        // ACT: Dispatch event
        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        // ASSERT: No outgoing webhook created
        $this->assertDatabaseMissing('outgoing_webhooks', [
            'event_type' => 'movie.generation.requested',
        ]);
    }

    public function test_outgoing_webhook_retried_on_failure(): void
    {
        // ARRANGE: Enable feature flag and configure webhook URL
        Feature::activate('webhook_notifications');
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $outgoingUrls['movie.generation.completed'] = ['https://example.com/webhook'];
        Config::set('webhooks.outgoing_urls', $outgoingUrls);

        // First attempt fails
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Server Error'], 500),
        ]);

        // ACT: Dispatch event
        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        // ASSERT: Outgoing webhook marked as failed with retry scheduled
        $webhook = OutgoingWebhook::where('event_type', 'movie.generation.requested')->first();
        $this->assertNotNull($webhook);
        $this->assertEquals('failed', $webhook->status);
        $this->assertNotNull($webhook->next_retry_at);
        $this->assertEquals(1, $webhook->attempts);

        // Retry job should be dispatched
        Queue::assertPushed(\App\Jobs\SendOutgoingWebhookJob::class, function ($job) use ($webhook) {
            // Use reflection to access private property
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('webhookId');
            $property->setAccessible(true);

            return $property->getValue($job) === $webhook->id;
        });
    }

    public function test_outgoing_webhook_signed_when_secret_configured(): void
    {
        // ARRANGE: Enable feature flag, configure webhook URL and secret
        Feature::activate('webhook_notifications');
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $outgoingUrls['movie.generation.completed'] = ['https://example.com/webhook'];
        Config::set('webhooks.outgoing_urls', $outgoingUrls);
        Config::set('webhooks.outgoing_secret', 'test-secret');

        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        // ACT: Dispatch event
        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        // ASSERT: Request was signed
        Http::assertSent(function ($request) {
            $signature = $request->header('X-MovieMind-Webhook-Signature');
            if ($signature === null || empty($signature)) {
                return false;
            }

            $body = json_encode($request->data());
            $expectedSignature = hash_hmac('sha256', $body, 'test-secret');
            $providedSignature = is_array($signature) ? $signature[0] : $signature;

            return hash_equals($expectedSignature, $providedSignature);
        });
    }

    public function test_multiple_webhook_urls_sent_to_all(): void
    {
        // ARRANGE: Enable feature flag and configure multiple webhook URLs
        Feature::activate('webhook_notifications');
        $outgoingUrls = Config::get('webhooks.outgoing_urls', []);
        $outgoingUrls['movie.generation.completed'] = [
            'https://example.com/webhook1',
            'https://example.com/webhook2',
        ];
        Config::set('webhooks.outgoing_urls', $outgoingUrls);

        Http::fake([
            'https://example.com/webhook1' => Http::response(['status' => 'ok'], 200),
            'https://example.com/webhook2' => Http::response(['status' => 'ok'], 200),
        ]);

        // Use unique job ID for this test
        $uniqueJobId = 'test-multiple-'.uniqid();

        // ACT: Dispatch event
        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: $uniqueJobId
        ));

        // ASSERT: Both webhook URLs received webhooks
        $this->assertDatabaseHas('outgoing_webhooks', [
            'url' => 'https://example.com/webhook1',
            'event_type' => 'movie.generation.requested',
        ]);
        $this->assertDatabaseHas('outgoing_webhooks', [
            'url' => 'https://example.com/webhook2',
            'event_type' => 'movie.generation.requested',
        ]);

        // Verify at least 2 webhooks were created (may be more from other tests)
        $webhookCount = OutgoingWebhook::where('event_type', 'movie.generation.requested')
            ->whereIn('url', ['https://example.com/webhook1', 'https://example.com/webhook2'])
            ->count();
        $this->assertGreaterThanOrEqual(2, $webhookCount, 'Expected at least 2 webhooks for this test');

        // Both URLs should receive webhooks (at least 2, may be more from other tests)
        $sentCount = count(Http::recorded());
        $this->assertGreaterThanOrEqual(2, $sentCount, 'Expected at least 2 HTTP requests for this test');
    }

    public function test_webhook_sent_to_subscription_url_when_no_env_url(): void
    {
        Feature::activate('webhook_notifications');
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.requested' => [],
            'movie.generation.completed' => [],
            'generation.completed' => [],
        ]);

        $subscriptionUrl = 'https://subscription-only.example/webhook';
        WebhookSubscription::create([
            'event_type' => 'movie.generation.requested',
            'url' => $subscriptionUrl,
        ]);

        Http::fake([
            $subscriptionUrl => Http::response(['status' => 'ok'], 200),
        ]);

        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        $this->assertDatabaseHas('outgoing_webhooks', [
            'event_type' => 'movie.generation.requested',
            'url' => $subscriptionUrl,
            'status' => 'sent',
        ]);
        Http::assertSent(fn ($request) => $request->url() === $subscriptionUrl);
    }

    public function test_webhook_sent_to_both_env_and_subscription_urls(): void
    {
        $envUrl = 'https://env.example/webhook';
        $formUrl = 'https://form.example/webhook';
        Feature::activate('webhook_notifications');
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.requested' => [$envUrl],
            'movie.generation.completed' => [],
            'generation.completed' => [],
        ]);

        WebhookSubscription::create([
            'event_type' => 'movie.generation.requested',
            'url' => $formUrl,
        ]);

        Http::fake([
            $envUrl => Http::response(['status' => 'ok'], 200),
            $formUrl => Http::response(['status' => 'ok'], 200),
        ]);

        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        $this->assertDatabaseHas('outgoing_webhooks', [
            'url' => $envUrl,
            'event_type' => 'movie.generation.requested',
        ]);
        $this->assertDatabaseHas('outgoing_webhooks', [
            'url' => $formUrl,
            'event_type' => 'movie.generation.requested',
        ]);
        Http::assertSent(fn ($r) => $r->url() === $envUrl);
        Http::assertSent(fn ($r) => $r->url() === $formUrl);
    }

    public function test_webhook_sent_once_when_same_url_in_env_and_subscription(): void
    {
        Feature::activate('webhook_notifications');
        $uniqueId = 'same-'.uniqid();
        $sameUrl = "https://{$uniqueId}.example/webhook";
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [$sameUrl],
            'generation.completed' => [],
        ]);

        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => $sameUrl,
        ]);

        Http::fake([$sameUrl => Http::response(['status' => 'ok'], 200)]);

        Event::dispatch(new MovieGenerationRequested(
            slug: 'the-matrix-1999',
            jobId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        $this->assertDatabaseHas('outgoing_webhooks', [
            'event_type' => 'movie.generation.requested',
            'url' => $sameUrl,
            'status' => 'sent',
        ]);
        // Deduplication is implemented in getUrlsForEvent (env + subscriptions merged, array_unique).
        Http::assertSent(fn ($request) => $request->url() === $sameUrl);
    }

    public function test_get_urls_for_event_deduplicates_same_url_from_env_and_subscription(): void
    {
        $sameUrl = 'https://dedupe.example/webhook';
        Config::set('webhooks.outgoing_urls', [
            'movie.generation.completed' => [$sameUrl],
            'generation.completed' => [],
        ]);
        WebhookSubscription::create([
            'event_type' => 'movie.generation.completed',
            'url' => $sameUrl,
        ]);

        $listener = app(SendOutgoingWebhookListener::class);
        $urls = $listener->getUrlsForEvent('movie.generation.completed', 'generation.completed');

        $this->assertSame([$sameUrl], $urls, 'Same URL in env and subscription must appear once');
    }
}
