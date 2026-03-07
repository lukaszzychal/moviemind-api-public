<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Base\AbstractRealGenerateTvContentJob;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\OpenAiClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Real Generate TV Series Job - calls actual AI API for production.
 * Inherits shared logic from abstract base class.
 */
class RealGenerateTvSeriesJob extends AbstractRealGenerateTvContentJob implements ShouldQueue
{
    protected function entityType(): string
    {
        return 'TV_SERIES';
    }

    protected function modelClass(): string
    {
        return TvSeries::class;
    }

    protected function descriptionModelClass(): string
    {
        return TvSeriesDescription::class;
    }

    protected function findExistingBySlug(string $slug, ?string $existingId): ?object
    {
        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);

        return $tvSeriesRepository->findBySlugForJob($slug, $existingId);
    }

    protected function callAiApi(OpenAiClientInterface $openAiClient, string $slug, ?array $tmdbData, string $locale, string $contextTag): array
    {
        return $openAiClient->generateTvSeries($slug, $tmdbData, $locale, $contextTag);
    }

    protected function descriptionForeignKey(): string
    {
        return 'tv_series_id';
    }

    protected function cachePrefix(): string
    {
        return 'tv_series';
    }
}
