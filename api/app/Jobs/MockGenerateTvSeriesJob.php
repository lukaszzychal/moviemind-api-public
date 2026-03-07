<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Base\AbstractMockGenerateTvContentJob;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mock Generate TV Series Job - simulates AI generation for dev/testing.
 * Inherits shared logic from abstract base class.
 */
class MockGenerateTvSeriesJob extends AbstractMockGenerateTvContentJob implements ShouldQueue
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

    protected function descriptionForeignKey(): string
    {
        return 'tv_series_id';
    }

    protected function cachePrefix(): string
    {
        return 'tv_series';
    }

    protected function generateMockDescriptionText(): string
    {
        $contextTagValue = $this->contextTag ?? 'default';

        if (strtolower($contextTagValue) === 'spoiler') {
            return "This is a detailed mock description for the TV series '{$this->slug}' containing spoilers.";
        }
        if (strtolower($contextTagValue) === 'short') {
            return "Mock short bio for '{$this->slug}'.";
        }

        return "This is a mock description for the TV series '{$this->slug}'. ".
            'It is automatically generated without calling the real OpenAI API.';
    }
}
