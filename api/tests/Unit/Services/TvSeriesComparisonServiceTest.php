<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\TvSeries;
use App\Repositories\TvSeriesRepository;
use App\Services\TvSeriesComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    private TvSeriesComparisonService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = $this->createMock(TvSeriesRepository::class);
        $this->service = new TvSeriesComparisonService($repository);
    }

    public function test_compare_returns_comparison_data(): void
    {
        // GIVEN: Two TV series with common genres and people
        $tvSeries1 = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
            'genres' => ['Drama', 'Crime', 'Thriller'],
            'first_air_date' => '2008-01-20',
        ]);

        $tvSeries2 = TvSeries::factory()->create([
            'title' => 'Better Call Saul',
            'slug' => 'better-call-saul-2015',
            'genres' => ['Drama', 'Crime'],
            'first_air_date' => '2015-02-08',
        ]);

        $repository = $this->createMock(TvSeriesRepository::class);
        $repository->method('findBySlugWithRelations')
            ->willReturnOnConsecutiveCalls($tvSeries1, $tvSeries2);

        $service = new TvSeriesComparisonService($repository);

        // WHEN: Comparing the two TV series
        $result = $service->compare('breaking-bad-2008', 'better-call-saul-2015');

        // THEN: Comparison data should be returned
        $this->assertArrayHasKey('tv_series1', $result);
        $this->assertArrayHasKey('tv_series2', $result);
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('common_genres', $result['comparison']);
        $this->assertArrayHasKey('similarity_score', $result['comparison']);
        $this->assertEquals('breaking-bad-2008', $result['tv_series1']['slug']);
        $this->assertEquals('better-call-saul-2015', $result['tv_series2']['slug']);
    }

    public function test_compare_throws_exception_when_tv_series_not_found(): void
    {
        // GIVEN: TV series don't exist
        $repository = $this->createMock(TvSeriesRepository::class);
        $repository->method('findBySlugWithRelations')
            ->willReturn(null);

        $service = new TvSeriesComparisonService($repository);

        // WHEN/THEN: Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('One or both TV series not found');

        $service->compare('non-existent-1', 'non-existent-2');
    }
}
