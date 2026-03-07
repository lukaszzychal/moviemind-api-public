<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Base\AbstractRealGenerateTvContentJob;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use App\Services\OpenAiClientInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Real Generate TV Show Job - calls actual AI API for production.
 * Inherits shared logic from abstract base class.
 */
class RealGenerateTvShowJob extends AbstractRealGenerateTvContentJob implements ShouldQueue
{
    protected function entityType(): string
    {
        return 'TV_SHOW';
    }

    protected function modelClass(): string
    {
        return TvShow::class;
    }

    protected function descriptionModelClass(): string
    {
        return TvShowDescription::class;
    }

    protected function findExistingBySlug(string $slug, ?string $existingId): ?object
    {
        /** @var TvShowRepository $tvShowRepository */
        $tvShowRepository = app(TvShowRepository::class);

        return $tvShowRepository->findBySlugForJob($slug, $existingId);
    }

    protected function callAiApi(OpenAiClientInterface $openAiClient, string $slug, ?array $tmdbData, string $locale, string $contextTag): array
    {
        return $openAiClient->generateTvShow($slug, $tmdbData, $locale, $contextTag);
    }

    protected function descriptionForeignKey(): string
    {
        return 'tv_show_id';
    }

    protected function cachePrefix(): string
    {
        return 'tv_show';
    }
}
