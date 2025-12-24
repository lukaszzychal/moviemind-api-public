<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\QueueTvSeriesGenerationAction;
use App\Enums\Locale;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\TmdbTvSeriesCreationService;
use App\Services\TvSeriesRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class TvSeriesRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_retrieve_tv_series_returns_cached_result_when_available(): void
    {
        $cachedData = ['id' => 1, 'title' => 'Cached TV Series'];
        $cacheKey = 'tv_series:test-slug:desc:default';
        Cache::put($cacheKey, $cachedData, 3600);

        $service = $this->createService();
        $result = $service->retrieveTvSeries('test-slug', null);

        $this->assertTrue($result->isCached());
        $this->assertEquals($cachedData, $result->getData());
    }

    public function test_retrieve_tv_series_returns_existing_tv_series_when_found_locally(): void
    {
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
            'first_air_date' => '2008-01-20',
        ]);

        $service = $this->createService();
        $result = $service->retrieveTvSeries('breaking-bad-2008', null);

        $this->assertFalse($result->isCached());
        $this->assertTrue($result->isFound());
        $this->assertEquals($tvSeries->id, $result->getTvSeries()?->id);
        $this->assertNull($result->getSelectedDescription());
    }

    public function test_retrieve_tv_series_returns_tv_series_with_selected_description(): void
    {
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
            'first_air_date' => '2008-01-20',
        ]);

        $description = TvSeriesDescription::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'locale' => Locale::EN_US,
            'text' => 'Test description',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        $service = $this->createService();
        $result = $service->retrieveTvSeries('breaking-bad-2008', $description->id);

        $this->assertTrue($result->isFound());
        $this->assertEquals($description->id, $result->getSelectedDescription()?->id);
    }

    public function test_retrieve_tv_series_returns_not_found_when_description_id_invalid(): void
    {
        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
            'first_air_date' => '2008-01-20',
        ]);

        $service = $this->createService();
        $nonExistentDescriptionId = '00000000-0000-0000-0000-000000000000';
        $result = $service->retrieveTvSeries('breaking-bad-2008', $nonExistentDescriptionId);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isDescriptionNotFound());
    }

    public function test_retrieve_tv_series_returns_not_found_when_tv_series_not_exists_and_feature_disabled(): void
    {
        Feature::define('ai_description_generation', false);

        $service = $this->createService();
        $result = $service->retrieveTvSeries('non-existent-tv-series', null);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isNotFound());
    }

    public function test_retrieve_tv_series_attempts_tmdb_when_tv_series_not_found_and_feature_enabled(): void
    {
        Feature::define('ai_description_generation', true);
        Feature::define('tmdb_verification', true);

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('verifyTvSeries')
            ->with('test-slug')
            ->willReturn(null);

        $tmdbService->expects($this->once())
            ->method('searchTvSeries')
            ->with('test-slug', 5)
            ->willReturn([]);

        $service = new TvSeriesRetrievalService(
            new TvSeriesRepository,
            $tmdbService,
            $this->createMock(TmdbTvSeriesCreationService::class),
            $this->createMock(QueueTvSeriesGenerationAction::class)
        );

        $result = $service->retrieveTvSeries('test-slug', null);

        $this->assertFalse($result->isFound());
    }

    private function createService(): TvSeriesRetrievalService
    {
        return new TvSeriesRetrievalService(
            new TvSeriesRepository,
            $this->createMock(EntityVerificationServiceInterface::class),
            $this->createMock(TmdbTvSeriesCreationService::class),
            $this->createMock(QueueTvSeriesGenerationAction::class)
        );
    }
}
