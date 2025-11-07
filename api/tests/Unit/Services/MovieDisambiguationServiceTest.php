<?php

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\MovieDisambiguationService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class MovieDisambiguationServiceTest extends TestCase
{
    public function test_returns_null_when_year_present(): void
    {
        $movie = Movie::make(['slug' => 'the-matrix-1999']);

        $repository = Mockery::mock(MovieRepository::class);
        $repository->shouldNotReceive('findAllByTitleSlug');

        $service = new MovieDisambiguationService($repository);

        $this->assertNull($service->determineMeta($movie, 'the-matrix-1999'));
    }

    public function test_returns_meta_when_ambiguous(): void
    {
        $movie = Movie::make(['slug' => 'the-matrix']);

        $otherMovies = new Collection([
            Movie::make(['slug' => 'the-matrix-1999', 'title' => 'The Matrix', 'release_year' => 1999]),
            Movie::make(['slug' => 'the-matrix-2003', 'title' => 'The Matrix', 'release_year' => 2003]),
        ]);

        $repository = Mockery::mock(MovieRepository::class);
        $repository->shouldReceive('findAllByTitleSlug')
            ->once()
            ->with('the-matrix')
            ->andReturn($otherMovies);

        $service = new MovieDisambiguationService($repository);

        $meta = $service->determineMeta($movie, 'the-matrix');

        $this->assertNotNull($meta);
        $this->assertTrue($meta['ambiguous']);
        $this->assertCount(2, $meta['alternatives']);
    }

    public function test_returns_null_when_only_one_movie(): void
    {
        $movie = Movie::make(['slug' => 'the-matrix']);

        $repository = Mockery::mock(MovieRepository::class);
        $repository->shouldReceive('findAllByTitleSlug')
            ->once()
            ->with('the-matrix')
            ->andReturn(new Collection([$movie]));

        $service = new MovieDisambiguationService($repository);

        $this->assertNull($service->determineMeta($movie, 'the-matrix'));
    }
}
