<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\MovieDisambiguationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieDisambiguationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_returns_null_when_year_present(): void
    {
        // Use real repository with test database (Chicago School)
        $repository = new MovieRepository;
        $service = new MovieDisambiguationService($repository);

        // Create movie with year in slug
        $movie = Movie::create([
            'slug' => 'the-matrix-1999',
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // When slug contains year, should return null (no disambiguation needed)
        $this->assertNull($service->determineMeta($movie, 'the-matrix-1999'));
    }

    public function test_returns_meta_when_ambiguous(): void
    {
        // Use real repository with test database (Chicago School)
        $repository = new MovieRepository;
        $service = new MovieDisambiguationService($repository);

        // Create multiple movies with same title but different years
        $movie1 = Movie::create([
            'slug' => 'the-matrix-1999',
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $movie2 = Movie::create([
            'slug' => 'the-matrix-2003',
            'title' => 'The Matrix',
            'release_year' => 2003,
        ]);

        // Create movie without year in slug (ambiguous)
        // Note: findAllByTitleSlug returns all movies matching the pattern, including this one
        $ambiguousMovie = Movie::create([
            'slug' => 'the-matrix',
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // When slug doesn't contain year and multiple movies exist, should return meta
        // findAllByTitleSlug('the-matrix') returns all 3 movies (the-matrix, the-matrix-1999, the-matrix-2003)
        $meta = $service->determineMeta($ambiguousMovie, 'the-matrix');

        $this->assertNotNull($meta);
        $this->assertTrue($meta['ambiguous']);
        // Should return all 3 movies (the-matrix, the-matrix-1999, the-matrix-2003)
        $this->assertCount(3, $meta['alternatives']);
    }

    public function test_returns_null_when_only_one_movie(): void
    {
        // Use real repository with test database (Chicago School)
        $repository = new MovieRepository;
        $service = new MovieDisambiguationService($repository);

        // Create single movie without year in slug
        $movie = Movie::create([
            'slug' => 'the-matrix',
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // When only one movie exists, should return null (no disambiguation needed)
        $this->assertNull($service->determineMeta($movie, 'the-matrix'));
    }
}
