<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\TvShow;
use App\Repositories\TvShowRepository;
use App\Services\TvShowComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    private TvShowComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->createMock(TvShowRepository::class);
        $this->service = new TvShowComparisonService($repository);
    }

    public function test_compare_returns_comparison_data(): void
    {
        // GIVEN: Two TV shows with common genres
        $tvShow1 = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
            'genres' => ['Talk', 'Variety'],
            'first_air_date' => '1954-09-27',
        ]);

        $tvShow2 = TvShow::factory()->create([
            'title' => 'The Late Show',
            'slug' => 'the-late-show-1993',
            'genres' => ['Talk'],
            'first_air_date' => '1993-08-30',
        ]);

        $repository = $this->createMock(TvShowRepository::class);
        $repository->method('findBySlugWithRelations')
            ->willReturnOnConsecutiveCalls($tvShow1, $tvShow2);

        $service = new TvShowComparisonService($repository);

        // WHEN: Comparing the two TV shows
        $result = $service->compare('the-tonight-show-1954', 'the-late-show-1993');

        // THEN: Comparison data should be returned
        $this->assertArrayHasKey('tv_show1', $result);
        $this->assertArrayHasKey('tv_show2', $result);
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('common_genres', $result['comparison']);
        $this->assertArrayHasKey('similarity_score', $result['comparison']);
    }

    public function test_compare_throws_exception_when_tv_show_not_found(): void
    {
        // GIVEN: TV shows don't exist
        $repository = $this->createMock(TvShowRepository::class);
        $repository->method('findBySlugWithRelations')
            ->willReturn(null);

        $service = new TvShowComparisonService($repository);

        // WHEN/THEN: Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('One or both TV shows not found');

        $service->compare('non-existent-1', 'non-existent-2');
    }
}
