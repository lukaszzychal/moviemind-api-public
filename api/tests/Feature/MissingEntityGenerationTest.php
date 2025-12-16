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
        config(['services.tmdb.api_key' => 'test-api-key']);
    }

    public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        Feature::activate('ai_description_generation');

        // Use fake EntityVerificationService instead of Mockery
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('annihilation', [
            'title' => 'Annihilation',
            'release_date' => '2018-02-23',
            'overview' => 'A biologist signs up for a dangerous expedition.',
            'id' => 300668,
            'director' => 'Alex Garland',
        ]);

        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level'])
            ->assertJson(['locale' => 'en-US']);

        // Verify confidence fields are set (not null/unknown)
        $this->assertNotNull($res->json('confidence'));
        $this->assertNotSame('unknown', $res->json('confidence_level'));
        $this->assertContains($res->json('confidence_level'), ['high', 'medium', 'low', 'very_low']);
    }

    public function test_movie_missing_returns_404_when_not_found_in_tmdb(): void
    {
        Feature::activate('ai_description_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService - set movie to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('non-existent-movie-xyz', null);
        // Also set empty search results which is called when verifyMovie returns null
        $fake->setMovieSearchResults('non-existent-movie-xyz', []);

        $res = $this->getJson('/api/v1/movies/non-existent-movie-xyz');
        $res->assertStatus(404)
            ->assertJson(['error' => 'Movie not found']);
    }

    public function test_movie_missing_returns_404_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');
        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(404);
    }

    public function test_person_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        Feature::activate('ai_bio_generation');

        // Use fake EntityVerificationService instead of Mockery
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('john-doe', [
            'name' => 'John Doe',
            'birthday' => '1980-01-01',
            'place_of_birth' => 'New York, USA',
            'id' => 123456,
            'biography' => 'An actor',
        ]);

        $res = $this->getJson('/api/v1/people/john-doe');
        $res->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level'])
            ->assertJson(['locale' => 'en-US']);

        // Verify confidence fields are set (not null/unknown)
        $this->assertNotNull($res->json('confidence'));
        $this->assertNotSame('unknown', $res->json('confidence_level'));
        $this->assertContains($res->json('confidence_level'), ['high', 'medium', 'low', 'very_low']);
    }

    public function test_person_missing_returns_404_when_not_found_in_tmdb(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::activate('tmdb_verification');

        // Use fake EntityVerificationService - set person to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('non-existent-person-xyz', null);

        $res = $this->getJson('/api/v1/people/non-existent-person-xyz');
        $res->assertStatus(404)
            ->assertJson(['error' => 'Person not found']);
    }

    public function test_movie_missing_reuses_active_job(): void
    {
        Feature::activate('ai_description_generation');

        // Use fake EntityVerificationService instead of Mockery
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('brand-new-movie', [
            'title' => 'Brand New Movie',
            'release_date' => '2024-01-01',
            'overview' => 'A brand new movie',
            'id' => 123456,
        ]);

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

        // Use fake EntityVerificationService instead of Mockery
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie($slug, [
            'title' => 'Concurrent Test Movie',
            'release_date' => '2024-01-01',
            'overview' => 'A test movie',
            'id' => 123456,
        ]);

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

        // Use fake EntityVerificationService instead of Mockery
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('concurrent-test-person', [
            'name' => 'Test Person',
            'birthday' => '1980-01-01',
            'place_of_birth' => 'Test City',
            'id' => 123,
        ]);

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

    public function test_movie_generation_bypasses_tmdb_when_feature_flag_disabled(): void
    {
        Feature::activate('ai_description_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService - set movie to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('non-existent-movie-xyz', null);

        // When tmdb_verification is disabled, it should bypass TMDb check and allow generation
        $res = $this->getJson('/api/v1/movies/non-existent-movie-xyz');
        $res->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level']);

        // Verify confidence fields are set (not null/unknown)
        $this->assertNotNull($res->json('confidence'));
        $this->assertNotSame('unknown', $res->json('confidence_level'));
    }

    public function test_person_generation_bypasses_tmdb_when_feature_flag_disabled(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService - set person to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('non-existent-person-xyz', null);

        // When tmdb_verification is disabled, it should bypass TMDb check and allow generation
        $res = $this->getJson('/api/v1/people/non-existent-person-xyz');
        $res->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level']);

        // Verify confidence fields are set (not null/unknown)
        $this->assertNotNull($res->json('confidence'));
        $this->assertNotSame('unknown', $res->json('confidence_level'));
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

    public function test_concurrent_requests_different_context_tag_different_jobs(): void
    {
        Feature::activate('ai_description_generation');

        // Use real cache (array driver) to test slot management mechanism
        config(['cache.default' => 'array']);
        Cache::clear();

        // Use Event::fake() to count dispatched events
        Event::fake();

        $slug = 'concurrent-different-context-movie';

        // Simulate concurrent requests with DIFFERENT context_tag
        $response1 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'modern',
        ]);
        $response2 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'humorous',
        ]); // Immediately after, but with different context_tag

        // Both should return 202
        $response1->assertStatus(202);
        $response2->assertStatus(202);

        // Both should return DIFFERENT job_id (different context_tag = different slots)
        $jobId1 = $response1->json('job_id');
        $jobId2 = $response2->json('job_id');
        $this->assertNotSame($jobId1, $jobId2, 'Concurrent requests with different context_tag should return different job_ids');

        // Verify both events were dispatched (different context_tag = different jobs)
        Event::assertDispatched(MovieGenerationRequested::class, 2);

        // Verify context_tag is correctly set in events
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->contextTag === 'modern';
        });
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->contextTag === 'humorous';
        });
    }

    public function test_multiple_context_tags_for_same_movie_allowed(): void
    {
        Feature::activate('ai_description_generation');

        // Use real cache to allow job processing
        config(['cache.default' => 'array']);
        Cache::clear();

        $slug = 'multi-context-movie';

        // Generate first description with modern context_tag
        $response1 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'modern',
        ]);
        $response1->assertStatus(202);
        $jobId1 = $response1->json('job_id');

        // Generate second description with humorous context_tag for the same movie
        $response2 = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'humorous',
        ]);
        $response2->assertStatus(202);
        $jobId2 = $response2->json('job_id');

        // Should return different job_ids
        $this->assertNotSame($jobId1, $jobId2);

        // After jobs complete, verify that both descriptions exist in database
        // (This test assumes jobs will complete - in real scenario, we'd wait or mock)
        // For now, we verify that both requests were accepted with different job_ids
        $this->assertNotEmpty($jobId1);
        $this->assertNotEmpty($jobId2);
    }
}
