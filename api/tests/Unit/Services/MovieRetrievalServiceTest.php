<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\QueueMovieGenerationAction;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\MovieRetrievalService;
use App\Services\TmdbMovieCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MovieRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_retrieve_movie_returns_cached_result_when_available(): void
    {
        $cachedData = ['id' => 1, 'title' => 'Cached Movie'];
        $cacheKey = 'movie:test-slug:desc:default';
        Cache::put($cacheKey, $cachedData, 3600);

        $service = $this->createService();
        $result = $service->retrieveMovie('test-slug', null);

        $this->assertTrue($result->isCached());
        $this->assertEquals($cachedData, $result->getData());
    }

    public function test_retrieve_movie_returns_existing_movie_when_found_locally(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $service = $this->createService();
        $result = $service->retrieveMovie('the-matrix-1999', null);

        $this->assertFalse($result->isCached());
        $this->assertTrue($result->isFound());
        $this->assertEquals($movie->id, $result->getMovie()?->id);
        $this->assertNull($result->getSelectedDescription());
    }

    public function test_retrieve_movie_returns_movie_with_selected_description(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $description = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'text' => 'Test description',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        $service = $this->createService();
        $result = $service->retrieveMovie('the-matrix-1999', $description->id);

        $this->assertTrue($result->isFound());
        $this->assertEquals($description->id, $result->getSelectedDescription()?->id);
    }

    public function test_retrieve_movie_returns_not_found_when_description_id_invalid(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $service = $this->createService();
        $result = $service->retrieveMovie('the-matrix-1999', 999);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isDescriptionNotFound());
    }

    public function test_retrieve_movie_returns_not_found_when_movie_not_exists_and_feature_disabled(): void
    {
        Feature::define('ai_description_generation', false);

        $service = $this->createService();
        $result = $service->retrieveMovie('non-existent-movie', null);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isNotFound());
    }

    public function test_retrieve_movie_attempts_tmdb_when_movie_not_found_and_feature_enabled(): void
    {
        Feature::define('ai_description_generation', true);
        Feature::define('tmdb_verification', true);

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('verifyMovie')
            ->with('test-slug')
            ->willReturn(null);

        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->with('test-slug', 5)
            ->willReturn([]);

        $service = new MovieRetrievalService(
            new MovieRepository,
            $tmdbService,
            $this->createMock(TmdbMovieCreationService::class),
            $this->createMock(QueueMovieGenerationAction::class)
        );

        $result = $service->retrieveMovie('test-slug', null);

        $this->assertFalse($result->isFound());
    }

    private function createService(): MovieRetrievalService
    {
        return new MovieRetrievalService(
            new MovieRepository,
            $this->createMock(EntityVerificationServiceInterface::class),
            $this->createMock(TmdbMovieCreationService::class),
            $this->createMock(QueueMovieGenerationAction::class)
        );
    }
}
