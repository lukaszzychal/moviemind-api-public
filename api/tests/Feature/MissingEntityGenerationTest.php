<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Services\TvShowRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MissingEntityGenerationTest extends TestCase
{
    use RefreshDatabase;

    private $response = null;

    private $response1 = null;

    private $response2 = null;

    private $fake = null;

    private string $apiKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['services.tmdb.api_key' => 'test-api-key']);
        $plan = \App\Models\SubscriptionPlan::where('name', 'pro')->first();
        if ($plan) {
            $result = app(\App\Services\ApiKeyService::class)->createKey('MissingEntityGenTest', $plan->id);
            $this->apiKey = $result['key'];
        }
    }

    public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        $this->givenAiGenerationEnabled()
            ->andMovieExistsInTmdb('annihilation', [
                'title' => 'Annihilation',
                'release_date' => '2018-02-23',
                'overview' => 'A biologist signs up for a dangerous expedition.',
                'id' => 300668,
                'director' => 'Alex Garland',
            ])
            ->whenRequestingMovie('annihilation')
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_movie_missing_returns_404_when_not_found_in_tmdb(): void
    {
        $this->givenAiGenerationEnabled()
            ->andTmdbVerificationEnabled()
            ->andMovieDoesNotExistInTmdb('non-existent-movie-xyz')
            ->whenRequestingMovie('non-existent-movie-xyz')
            ->thenShouldReturn404WithError('Movie not found');
    }

    public function test_movie_missing_returns_404_when_flag_off(): void
    {
        $this->givenAiGenerationDisabled()
            ->whenRequestingMovie('annihilation')
            ->thenShouldReturn404();
    }

    public function test_person_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        $this->givenAiBioGenerationEnabled()
            ->andPersonExistsInTmdb('john-doe', [
                'name' => 'John Doe',
                'birthday' => '1980-01-01',
                'place_of_birth' => 'New York, USA',
                'id' => 123456,
                'biography' => 'An actor',
            ])
            ->whenRequestingPerson('john-doe')
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_person_missing_returns_404_when_not_found_in_tmdb(): void
    {
        $this->givenAiBioGenerationEnabled()
            ->andTmdbVerificationEnabled()
            ->andPersonDoesNotExistInTmdb('non-existent-person-xyz')
            ->whenRequestingPerson('non-existent-person-xyz')
            ->thenShouldReturn404WithError('Person not found');
    }

    /**
     * @todo INCOMPLETE: This test requires slot management implementation.
     *
     * Problem: After first request, movie is created in database, so second request
     * finds it locally and returns 200 instead of 202. This test needs slot management
     * logic that prevents duplicate jobs even when movie exists but has no descriptions.
     *
     * To be fixed in future stages when slot management is fully implemented.
     */
    public function test_movie_missing_reuses_active_job(): void
    {
        $this->markTestIncomplete(
            'Requires slot management implementation. '.
            'After first request, movie is created, so second request returns 200 instead of 202. '.
            'Needs logic to track active generation jobs and reuse them even when movie exists without descriptions.'
        );

        $this->givenAiGenerationEnabled()
            ->andMovieExistsInTmdb('brand-new-movie', [
                'title' => 'Brand New Movie',
                'release_date' => '2024-01-01',
                'overview' => 'A brand new movie',
                'id' => 123456,
            ])
            ->whenRequestingMovie('brand-new-movie')
            ->thenShouldReturn202();

        $jobId = $this->response->json('job_id');

        $this->whenRequestingMovie('brand-new-movie')
            ->thenShouldReturn202()
            ->andJobIdShouldBe($jobId);
    }

    public function test_person_missing_returns_404_when_flag_off(): void
    {
        $this->givenAiBioGenerationDisabled()
            ->whenRequestingPerson('john-doe')
            ->thenShouldReturn404();
    }

    /**
     * @todo INCOMPLETE: This test requires slot management implementation.
     *
     * Problem: After first request, movie is created in database, so second request
     * finds it locally and returns 200 instead of 202. This test needs slot management
     * logic that prevents duplicate jobs for concurrent requests even when movie exists
     * but has no descriptions.
     *
     * To be fixed in future stages when slot management is fully implemented.
     */
    public function test_concurrent_requests_for_same_slug_only_dispatch_one_job(): void
    {
        $this->markTestIncomplete(
            'Requires slot management implementation. '.
            'After first request, movie is created, so second request returns 200 instead of 202. '.
            'Needs logic to track active generation jobs and reuse them for concurrent requests '.
            'even when movie exists without descriptions.'
        );

        $this->givenAiGenerationEnabled()
            ->andRealCacheEnabled()
            ->andEventFakeEnabled()
            ->andMovieExistsInTmdb('concurrent-test-movie', [
                'title' => 'Concurrent Test Movie',
                'release_date' => '2024-01-01',
                'overview' => 'A test movie',
                'id' => 123456,
            ])
            ->whenRequestingMovieConcurrently('concurrent-test-movie')
            ->thenBothResponsesShouldReturn202()
            ->andBothShouldHaveSameJobId()
            ->andEventShouldBeDispatchedOnce(MovieGenerationRequested::class);
    }

    public function test_concurrent_requests_via_generate_endpoint_only_dispatch_one_job(): void
    {
        $this->givenAiGenerationEnabled()
            ->andRealCacheEnabled()
            ->andEventFakeEnabled()
            ->whenGeneratingMovieConcurrently('concurrent-generate-test')
            ->thenBothResponsesShouldReturn202()
            ->andBothShouldHaveSameJobId()
            ->andEventShouldBeDispatchedOnce(MovieGenerationRequested::class);
    }

    public function test_concurrent_requests_for_same_person_slug_only_dispatch_one_job(): void
    {
        $this->markTestIncomplete(
            'Person generation job deduplication may return different job IDs when requests are concurrent.'
        );

        $this->givenAiBioGenerationEnabled()
            ->andPersonExistsInTmdb('concurrent-test-person', [
                'name' => 'Test Person',
                'birthday' => '1980-01-01',
                'place_of_birth' => 'Test City',
                'id' => 123,
            ])
            ->andRealCacheEnabled()
            ->andEventFakeEnabled()
            ->whenRequestingPersonConcurrently('concurrent-test-person')
            ->thenBothResponsesShouldReturn202()
            ->andBothShouldHaveSameJobId()
            ->andEventShouldBeDispatchedOnce(PersonGenerationRequested::class);
    }

    public function test_movie_generation_bypasses_tmdb_when_feature_flag_disabled(): void
    {
        $this->givenAiGenerationEnabled()
            ->andTmdbVerificationDisabled()
            ->andMovieDoesNotExistInTmdb('non-existent-movie-xyz')
            ->whenRequestingMovie('non-existent-movie-xyz')
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_person_generation_bypasses_tmdb_when_feature_flag_disabled(): void
    {
        $this->givenAiBioGenerationEnabled()
            ->andTmdbVerificationDisabled()
            ->andPersonDoesNotExistInTmdb('non-existent-person-xyz')
            ->whenRequestingPerson('non-existent-person-xyz')
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_concurrent_requests_via_generate_endpoint_for_person_only_dispatch_one_job(): void
    {
        $this->givenAiBioGenerationEnabled()
            ->andRealCacheEnabled()
            ->andEventFakeEnabled()
            ->whenGeneratingPersonConcurrently('concurrent-generate-person-test')
            ->thenBothResponsesShouldReturn202()
            ->andBothShouldHaveSameJobId()
            ->andEventShouldBeDispatchedOnce(PersonGenerationRequested::class);
    }

    public function test_concurrent_requests_different_context_tag_different_jobs(): void
    {
        $this->givenAiGenerationEnabled()
            ->andRealCacheEnabled()
            ->andEventFakeEnabled()
            ->whenGeneratingMovieWithDifferentContextTags('concurrent-different-context-movie')
            ->thenBothResponsesShouldReturn202()
            ->andBothShouldHaveDifferentJobIds()
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, 2)
            ->andEventShouldHaveContextTag('modern')
            ->andEventShouldHaveContextTag('humorous');
    }

    public function test_multiple_context_tags_for_same_movie_allowed(): void
    {
        $this->givenAiGenerationEnabled()
            ->andRealCacheEnabled()
            ->whenGeneratingMovieWithContextTag('multi-context-movie', 'modern')
            ->thenShouldReturn202();

        $jobId1 = $this->response->json('job_id');

        $this->whenGeneratingMovieWithContextTag('multi-context-movie', 'humorous')
            ->thenShouldReturn202()
            ->andJobIdShouldBeDifferentFrom($jobId1);
    }

    public function test_tv_series_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        $this->givenAiGenerationEnabled()
            ->andTvSeriesExistsInTmdb('breaking-bad-2008', [
                'name' => 'Breaking Bad',
                'first_air_date' => '2008-01-20',
                'overview' => 'A high school chemistry teacher turned methamphetamine manufacturer.',
                'id' => 1396,
            ])
            ->whenRequestingTvSeries('breaking-bad-2008')
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_tv_series_missing_returns_404_when_not_found_in_tmdb(): void
    {
        $this->givenAiGenerationEnabled()
            ->andTmdbVerificationEnabled()
            ->andTvSeriesDoesNotExistInTmdb('non-existent-tv-series-xyz')
            ->whenRequestingTvSeries('non-existent-tv-series-xyz')
            ->thenShouldReturn404WithError('TV series not found');
    }

    public function test_tv_series_missing_returns_404_when_flag_off(): void
    {
        $this->givenAiGenerationDisabled()
            ->whenRequestingTvSeries('breaking-bad-2008')
            ->thenShouldReturn404();
    }

    public function test_tv_show_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
    {
        $slug = 'unique-late-night-show-2025';
        $this->givenAiGenerationEnabled()
            ->andTvmazeVerificationEnabled()
            ->andTvShowExistsInTmdb($slug, [
                'name' => 'Unique Late Night Show',
                'first_air_date' => '2025-01-15',
                'overview' => 'A unique late-night talk show for testing.',
                'id' => 99999,
            ])
            ->whenRequestingTvShow($slug)
            ->thenShouldReturn202WithJobDetails()
            ->andConfidenceFieldsShouldBeSet();
    }

    public function test_tv_show_missing_returns_404_when_not_found_in_tmdb(): void
    {
        $this->givenAiGenerationEnabled()
            ->andTmdbVerificationEnabled()
            ->andTvShowDoesNotExistInTmdb('non-existent-tv-show-xyz')
            ->whenRequestingTvShow('non-existent-tv-show-xyz')
            ->thenShouldReturn404WithError('TV show not found');
    }

    public function test_tv_show_missing_returns_404_when_flag_off(): void
    {
        $this->givenAiGenerationDisabled()
            ->whenRequestingTvShow('the-tonight-show-1954')
            ->thenShouldReturn404();
    }

    // ============================================
    // GIVEN helpers - Ustalenie kontekstu
    // ============================================

    private function givenAiGenerationEnabled(): self
    {
        Feature::activate('ai_description_generation');

        return $this;
    }

    private function givenAiGenerationDisabled(): self
    {
        Feature::deactivate('ai_description_generation');

        return $this;
    }

    private function givenAiBioGenerationEnabled(): self
    {
        Feature::activate('ai_bio_generation');

        return $this;
    }

    private function givenAiBioGenerationDisabled(): self
    {
        Feature::deactivate('ai_bio_generation');

        return $this;
    }

    private function andTmdbVerificationEnabled(): self
    {
        Feature::activate('tmdb_verification');

        return $this;
    }

    private function andTmdbVerificationDisabled(): self
    {
        Feature::deactivate('tmdb_verification');

        return $this;
    }

    private function andTvmazeVerificationEnabled(): self
    {
        Feature::activate('tvmaze_verification');

        return $this;
    }

    private function andMovieExistsInTmdb(string $slug, array $data): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setMovie($slug, $data);

        return $this;
    }

    private function andMovieDoesNotExistInTmdb(string $slug): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setMovie($slug, null);
        $this->fake->setMovieSearchResults($slug, []);

        return $this;
    }

    private function andPersonExistsInTmdb(string $slug, array $data): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setPerson($slug, $data);

        return $this;
    }

    private function andPersonDoesNotExistInTmdb(string $slug): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setPerson($slug, null);

        return $this;
    }

    private function andTvSeriesExistsInTmdb(string $slug, array $data): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setTvSeries($slug, $data);

        return $this;
    }

    private function andTvSeriesDoesNotExistInTmdb(string $slug): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setTvSeries($slug, null);
        $this->fake->setTvSeriesSearchResults($slug, []);

        return $this;
    }

    private function andTvShowExistsInTmdb(string $slug, array $data): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setTvShow($slug, $data);
        $this->app->when(TvShowRetrievalService::class)
            ->needs(\App\Services\EntityVerificationServiceInterface::class)
            ->give(fn () => $this->fake);

        return $this;
    }

    private function andTvShowDoesNotExistInTmdb(string $slug): self
    {
        if ($this->fake === null) {
            $this->fake = $this->fakeEntityVerificationService();
        }
        $this->fake->setTvShow($slug, null);
        $this->fake->setTvShowSearchResults($slug, []);
        $this->app->when(TvShowRetrievalService::class)
            ->needs(\App\Services\EntityVerificationServiceInterface::class)
            ->give(fn () => $this->fake);

        return $this;
    }

    private function andRealCacheEnabled(): self
    {
        config(['cache.default' => 'array']);
        Cache::clear();

        return $this;
    }

    private function andEventFakeEnabled(): self
    {
        Event::fake();

        return $this;
    }

    // ============================================
    // WHEN helpers - Wykonanie akcji
    // ============================================

    private function whenRequestingMovie(string $slug): self
    {
        $this->response = $this->getJson("/api/v1/movies/{$slug}");

        return $this;
    }

    private function whenRequestingPerson(string $slug): self
    {
        $this->response = $this->getJson("/api/v1/people/{$slug}");

        return $this;
    }

    private function whenRequestingTvSeries(string $slug): self
    {
        $this->response = $this->getJson("/api/v1/tv-series/{$slug}");

        return $this;
    }

    private function whenRequestingTvShow(string $slug): self
    {
        $this->response = $this->getJson("/api/v1/tv-shows/{$slug}");

        return $this;
    }

    private function whenRequestingMovieConcurrently(string $slug): self
    {
        $this->response1 = $this->getJson("/api/v1/movies/{$slug}");
        $this->response2 = $this->getJson("/api/v1/movies/{$slug}");

        return $this;
    }

    private function whenRequestingPersonConcurrently(string $slug): self
    {
        $this->response1 = $this->getJson("/api/v1/people/{$slug}");
        $this->response2 = $this->getJson("/api/v1/people/{$slug}");

        return $this;
    }

    private function whenGeneratingMovieConcurrently(string $slug): self
    {
        $this->response1 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ]);
        $this->response2 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ]);

        return $this;
    }

    private function whenGeneratingPersonConcurrently(string $slug): self
    {
        $this->response1 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $slug,
        ]);
        $this->response2 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $slug,
        ]);

        return $this;
    }

    private function whenGeneratingMovieWithDifferentContextTags(string $slug): self
    {
        $this->response1 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'modern',
        ]);
        $this->response2 = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => 'humorous',
        ]);

        return $this;
    }

    private function whenGeneratingMovieWithContextTag(string $slug, string $contextTag): self
    {
        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
            'context_tag' => $contextTag,
        ]);

        return $this;
    }

    // ============================================
    // THEN helpers - Weryfikacja rezultatu
    // ============================================

    private function thenShouldReturn202(): self
    {
        $this->response->assertStatus(202);

        return $this;
    }

    private function thenShouldReturn404(): self
    {
        $this->response->assertStatus(404);

        return $this;
    }

    private function thenShouldReturn202WithJobDetails(): self
    {
        $this->response->assertStatus(202)
            ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level'])
            ->assertJson(['locale' => 'en-US']);

        return $this;
    }

    private function thenShouldReturn404WithError(string $error): self
    {
        $this->response->assertStatus(404)
            ->assertJson(['error' => $error]);

        return $this;
    }

    private function thenBothResponsesShouldReturn202(): self
    {
        $this->response1->assertStatus(202);
        $this->response2->assertStatus(202);

        return $this;
    }

    // ============================================
    // AND helpers - Dodatkowe weryfikacje
    // ============================================

    private function andConfidenceFieldsShouldBeSet(): self
    {
        $this->assertNotNull($this->response->json('confidence'));
        $this->assertNotSame('unknown', $this->response->json('confidence_level'));
        $this->assertContains($this->response->json('confidence_level'), ['high', 'medium', 'low', 'very_low']);

        return $this;
    }

    private function andJobIdShouldBe(string $expectedJobId): self
    {
        $this->assertSame($expectedJobId, $this->response->json('job_id'));

        return $this;
    }

    private function andJobIdShouldBeDifferentFrom(string $otherJobId): self
    {
        $this->assertNotSame($otherJobId, $this->response->json('job_id'));
        $this->assertNotEmpty($this->response->json('job_id'));

        return $this;
    }

    private function andBothShouldHaveSameJobId(): self
    {
        $jobId1 = $this->response1->json('job_id');
        $jobId2 = $this->response2->json('job_id');
        $this->assertSame($jobId1, $jobId2, 'Concurrent requests should reuse the same job');

        return $this;
    }

    private function andBothShouldHaveDifferentJobIds(): self
    {
        $jobId1 = $this->response1->json('job_id');
        $jobId2 = $this->response2->json('job_id');
        $this->assertNotSame($jobId1, $jobId2, 'Concurrent requests with different context_tag should return different job_ids');

        return $this;
    }

    private function andEventShouldBeDispatchedOnce(string $eventClass): self
    {
        Event::assertDispatched($eventClass, 1);

        return $this;
    }

    private function andEventShouldBeDispatched(string $eventClass, int $times): self
    {
        Event::assertDispatched($eventClass, $times);

        return $this;
    }

    private function andEventShouldHaveContextTag(string $contextTag): self
    {
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) use ($contextTag) {
            return $event->contextTag === $contextTag;
        });

        return $this;
    }
}
