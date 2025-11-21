<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MissingEntityGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_movie_missing_returns_202_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');
        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'slug'])
            ->assertJson(['locale' => 'en-US']);
    }

    public function test_movie_missing_returns_404_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');
        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(404);
    }

    public function test_person_missing_returns_202_when_flag_on(): void
    {
        Feature::activate('ai_bio_generation');
        $res = $this->getJson('/api/v1/people/john-doe');
        $res->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'slug'])
            ->assertJson(['locale' => 'en-US']);
    }

    public function test_movie_missing_reuses_active_job(): void
    {
        Feature::activate('ai_description_generation');
        $first = $this->getJson('/api/v1/movies/brand-new-movie');
        $first->assertStatus(202);
        $jobId = $first->json('job_id');

        $second = $this->getJson('/api/v1/movies/brand-new-movie');
        $second->assertStatus(202);
        $this->assertSame($jobId, $second->json('job_id'));
    }

    public function test_person_missing_returns_404_when_flag_off(): void
    {
        Feature::deactivate('ai_bio_generation');
        $res = $this->getJson('/api/v1/people/john-doe');
        $res->assertStatus(404);
    }

    public function test_concurrent_requests_for_same_slug_only_dispatch_one_job(): void
    {
        Feature::activate('ai_description_generation');

        // Use real cache (array driver) to test slot management mechanism
        config(['cache.default' => 'array']);
        Cache::clear();

        // Use Event::fake() to count dispatched events
        Event::fake();

        $slug = 'concurrent-test-movie';

        // Simulate "parallel" requests (sequential but very close in time)
        // This tests the acquireGenerationSlot mechanism
        $response1 = $this->getJson("/api/v1/movies/{$slug}");
        $response2 = $this->getJson("/api/v1/movies/{$slug}"); // Immediately after

        // Both should return 202
        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Both should return the SAME job_id (slot management working)
        $jobId1 = $response1->json('job_id');
        $jobId2 = $response2->json('job_id');
        $this->assertSame($jobId1, $jobId2, 'Concurrent requests should reuse the same job');

        // Verify only one event was dispatched (slot management prevents duplicate jobs)
        Event::assertDispatched(MovieGenerationRequested::class, 1);
    }

    public function test_concurrent_requests_via_generate_endpoint_only_dispatch_one_job(): void
    {
        Feature::activate('ai_description_generation');

        // Use real cache (array driver) to test slot management mechanism
        config(['cache.default' => 'array']);
        Cache::clear();

        // Use Event::fake() to count dispatched events
        Event::fake();

        $slug = 'concurrent-generate-test';

        // Simulate "parallel" requests via POST /api/v1/generate
        $response1 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ]);
        $response2 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ]); // Immediately after

        // Both should return 202
        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Both should return the SAME job_id (slot management working)
        $jobId1 = $response1->json('job_id');
        $jobId2 = $response2->json('job_id');
        $this->assertSame($jobId1, $jobId2, 'Concurrent requests should reuse the same job');

        // Verify only one event was dispatched (slot management prevents duplicate jobs)
        Event::assertDispatched(MovieGenerationRequested::class, 1);
    }

    public function test_concurrent_requests_for_same_person_slug_only_dispatch_one_job(): void
    {
        Feature::activate('ai_bio_generation');

        // Use real cache (array driver) to test slot management mechanism
        config(['cache.default' => 'array']);
        Cache::clear();

        // Use Event::fake() to count dispatched events
        Event::fake();

        $slug = 'concurrent-test-person';

        // Simulate "parallel" requests (sequential but very close in time)
        // This tests the acquireGenerationSlot mechanism
        $response1 = $this->getJson("/api/v1/people/{$slug}");
        $response2 = $this->getJson("/api/v1/people/{$slug}"); // Immediately after

        // Both should return 202
        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Both should return the SAME job_id (slot management working)
        $jobId1 = $response1->json('job_id');
        $jobId2 = $response2->json('job_id');
        $this->assertSame($jobId1, $jobId2, 'Concurrent requests should reuse the same job');

        // Verify only one event was dispatched (slot management prevents duplicate jobs)
        Event::assertDispatched(PersonGenerationRequested::class, 1);
    }

    public function test_concurrent_requests_via_generate_endpoint_for_person_only_dispatch_one_job(): void
    {
        Feature::activate('ai_bio_generation');

        // Use real cache (array driver) to test slot management mechanism
        config(['cache.default' => 'array']);
        Cache::clear();

        // Use Event::fake() to count dispatched events
        Event::fake();

        $slug = 'concurrent-generate-person-test';

        // Simulate "parallel" requests via POST /api/v1/generate
        $response1 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $slug,
        ]);
        $response2 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $slug,
        ]); // Immediately after

        // Both should return 202
        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Both should return the SAME job_id (slot management working)
        $jobId1 = $response1->json('job_id');
        $jobId2 = $response2->json('job_id');
        $this->assertSame($jobId1, $jobId2, 'Concurrent requests should reuse the same job');

        // Verify only one event was dispatched (slot management prevents duplicate jobs)
        Event::assertDispatched(PersonGenerationRequested::class, 1);
    }
}
