<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\QueueTvShowGenerationAction;
use App\Enums\Locale;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\TmdbTvShowCreationService;
use App\Services\TvShowRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class TvShowRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_retrieve_tv_show_returns_cached_result_when_available(): void
    {
        $cachedData = ['id' => 1, 'title' => 'Cached TV Show'];
        $cacheKey = 'tv_show:test-slug:desc:default';
        Cache::put($cacheKey, $cachedData, 3600);

        $service = $this->createService();
        $result = $service->retrieveTvShow('test-slug', null);

        $this->assertTrue($result->isCached());
        $this->assertEquals($cachedData, $result->getData());
    }

    public function test_retrieve_tv_show_returns_existing_tv_show_when_found_locally(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
            'first_air_date' => '1954-09-27',
        ]);

        $service = $this->createService();
        $result = $service->retrieveTvShow('the-tonight-show-1954', null);

        $this->assertFalse($result->isCached());
        $this->assertTrue($result->isFound());
        $this->assertEquals($tvShow->id, $result->getTvShow()?->id);
        $this->assertNull($result->getSelectedDescription());
    }

    public function test_retrieve_tv_show_returns_tv_show_with_selected_description(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
            'first_air_date' => '1954-09-27',
        ]);

        $description = TvShowDescription::factory()->create([
            'tv_show_id' => $tvShow->id,
            'locale' => Locale::EN_US,
            'text' => 'Test description',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        $service = $this->createService();
        $result = $service->retrieveTvShow('the-tonight-show-1954', $description->id);

        $this->assertTrue($result->isFound());
        $this->assertEquals($description->id, $result->getSelectedDescription()?->id);
    }

    public function test_retrieve_tv_show_returns_not_found_when_description_id_invalid(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
            'first_air_date' => '1954-09-27',
        ]);

        $service = $this->createService();
        $nonExistentDescriptionId = '00000000-0000-0000-0000-000000000000';
        $result = $service->retrieveTvShow('the-tonight-show-1954', $nonExistentDescriptionId);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isDescriptionNotFound());
    }

    public function test_retrieve_tv_show_returns_not_found_when_tv_show_not_exists_and_feature_disabled(): void
    {
        Feature::define('ai_description_generation', false);

        $service = $this->createService();
        $result = $service->retrieveTvShow('non-existent-tv-show', null);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isNotFound());
    }

    public function test_retrieve_tv_show_attempts_tmdb_when_tv_show_not_found_and_feature_enabled(): void
    {
        Feature::define('ai_description_generation', true);
        Feature::define('tmdb_verification', true);

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('verifyTvShow')
            ->with('test-slug')
            ->willReturn(null);

        $tmdbService->expects($this->once())
            ->method('searchTvShows')
            ->with('test-slug', 5)
            ->willReturn([]);

        $service = new TvShowRetrievalService(
            new TvShowRepository,
            $tmdbService,
            $this->createMock(TmdbTvShowCreationService::class),
            $this->createMock(QueueTvShowGenerationAction::class)
        );

        $result = $service->retrieveTvShow('test-slug', null);

        $this->assertFalse($result->isFound());
    }

    private function createService(): TvShowRetrievalService
    {
        return new TvShowRetrievalService(
            new TvShowRepository,
            $this->createMock(EntityVerificationServiceInterface::class),
            $this->createMock(TmdbTvShowCreationService::class),
            $this->createMock(QueueTvShowGenerationAction::class)
        );
    }
}
