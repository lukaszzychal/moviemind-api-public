<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\QueueTvSeriesGenerationAction;
use App\Enums\Locale;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\TvSeriesRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    private function createService(): TvSeriesRetrievalService
    {
        return new TvSeriesRetrievalService(
            new TvSeriesRepository,
            $this->createMock(QueueTvSeriesGenerationAction::class)
        );
    }
}
