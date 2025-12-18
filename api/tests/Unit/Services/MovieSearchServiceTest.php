<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\MovieSearchService;
use App\Support\SearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MovieSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_search_returns_search_result(): void
    {
        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix']);

        $this->assertInstanceOf(SearchResult::class, $result);
    }

    public function test_search_merges_local_and_external_results(): void
    {
        // Create a local movie
        $localMovie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
            'director' => 'Wachowski',
        ]);

        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([$localMovie]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([
                [
                    'title' => 'The Matrix Reloaded',
                    'release_date' => '2003-05-15',
                    'overview' => 'Neo continues his journey',
                    'id' => 604,
                    'director' => 'Wachowski',
                ],
            ]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix']);

        $this->assertInstanceOf(SearchResult::class, $result);
        $this->assertGreaterThan(0, $result->total);
        $this->assertEquals(1, $result->localCount);
        $this->assertEquals(1, $result->externalCount);
    }

    public function test_search_filters_by_year(): void
    {
        $movie1999 = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $movie2003 = Movie::create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003',
            'release_year' => 2003,
        ]);

        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([$movie1999, $movie2003]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix', 'year' => 1999]);

        // Should only return 1999 movie
        $this->assertEquals(1, $result->localCount);
        foreach ($result->results as $resultItem) {
            if ($resultItem['source'] === 'local' && isset($resultItem['release_year'])) {
                $this->assertEquals(1999, $resultItem['release_year']);
            }
        }
    }

    public function test_search_filters_tmdb_results_by_year(): void
    {
        // Given: No local movies, but TMDB returns movies from different years
        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([
                [
                    'title' => 'The Matrix',
                    'release_date' => '1999-03-31',
                    'overview' => 'A hacker discovers...',
                    'id' => 603,
                    'director' => 'Wachowski',
                ],
                [
                    'title' => 'The Matrix Reloaded',
                    'release_date' => '2003-05-15',
                    'overview' => 'Neo continues...',
                    'id' => 604,
                    'director' => 'Wachowski',
                ],
                [
                    'title' => 'The Matrix Revolutions',
                    'release_date' => '2003-11-05',
                    'overview' => 'The war ends...',
                    'id' => 605,
                    'director' => 'Wachowski',
                ],
            ]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        // When: Searching with year=1999
        $result = $service->search(['q' => 'Matrix', 'year' => 1999]);

        // Then: Should only return 1999 movie from TMDB
        $this->assertEquals(0, $result->localCount);
        $this->assertEquals(1, $result->externalCount);
        $this->assertEquals(1, $result->total);

        foreach ($result->results as $resultItem) {
            if ($resultItem['source'] === 'external' && isset($resultItem['release_year'])) {
                $this->assertEquals(1999, $resultItem['release_year'], 'External result should be filtered by year');
            }
        }
    }

    public function test_search_filters_tmdb_results_by_year_returns_empty_when_no_match(): void
    {
        // Given: No local movies, TMDB returns movies but none match the year filter
        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([
                [
                    'title' => 'The Matrix',
                    'release_date' => '1999-03-31',
                    'overview' => 'A hacker discovers...',
                    'id' => 603,
                    'director' => 'Wachowski',
                ],
                [
                    'title' => 'The Matrix Reloaded',
                    'release_date' => '2003-05-15',
                    'overview' => 'Neo continues...',
                    'id' => 604,
                    'director' => 'Wachowski',
                ],
            ]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        // When: Searching with year=1991 (no movies from this year)
        $result = $service->search(['q' => 'Matrix', 'year' => 1991]);

        // Then: Should return empty results
        $this->assertEquals(0, $result->localCount);
        $this->assertEquals(0, $result->externalCount);
        $this->assertEquals(0, $result->total);
        $this->assertEquals('none', $result->matchType);
    }

    public function test_search_determines_exact_match(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([$movie]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix', 'year' => 1999]);

        $this->assertEquals('exact', $result->matchType);
        $this->assertEquals(1.0, $result->confidence);
    }

    public function test_search_determines_ambiguous_match(): void
    {
        $movie1 = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $movie2 = Movie::create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003',
            'release_year' => 2003,
        ]);

        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([$movie1, $movie2]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix']);

        $this->assertEquals('ambiguous', $result->matchType);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence ?? 0);
    }

    public function test_search_determines_none_match(): void
    {
        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'NonexistentMovieXYZ']);

        $this->assertEquals('none', $result->matchType);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function test_search_caches_results(): void
    {
        Cache::flush();

        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
        ]);

        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once()) // Should only be called once due to cache
            ->method('searchMovies')
            ->willReturn(Collection::make([$movie]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once()) // Should only be called once due to cache
            ->method('searchMovies')
            ->willReturn([]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        // First call
        $result1 = $service->search(['q' => 'Matrix']);

        // Second call (should use cache)
        $result2 = $service->search(['q' => 'Matrix']);

        $this->assertEquals($result1->total, $result2->total);
    }

    public function test_search_external_results_do_not_contain_tmdb_id(): void
    {
        $movieRepository = $this->createMock(MovieRepository::class);
        $movieRepository->expects($this->once())
            ->method('searchMovies')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchMovies')
            ->willReturn([
                [
                    'title' => 'The Matrix',
                    'release_date' => '1999-03-31',
                    'overview' => 'A hacker discovers...',
                    'id' => 603, // This is tmdb_id - should NOT appear in results
                    'director' => 'Wachowski',
                ],
            ]);

        Feature::define('tmdb_verification', true);

        $service = new MovieSearchService($movieRepository, $tmdbService);

        $result = $service->search(['q' => 'Matrix']);

        foreach ($result->results as $resultItem) {
            if ($resultItem['source'] === 'external') {
                $this->assertArrayNotHasKey('tmdb_id', $resultItem);
                $this->assertArrayNotHasKey('id', $resultItem); // 'id' is tmdb_id
            }
        }
    }
}
