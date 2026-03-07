<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Base\AbstractMockGenerateTvContentJob;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Mock Generate TV Show Job - simulates AI generation for dev/testing.
 * Inherits shared logic from abstract base class.
 */
class MockGenerateTvShowJob extends AbstractMockGenerateTvContentJob implements ShouldQueue
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

    protected function descriptionForeignKey(): string
    {
        return 'tv_show_id';
    }

    protected function cachePrefix(): string
    {
        return 'tv_show';
    }

    protected function generateMockDescriptionText(): string
    {
        $contextTagValue = $this->contextTag ?? 'default';

        if (strtolower($contextTagValue) === 'spoiler') {
            return "This is a detailed mock description for the TV show '{$this->slug}' containing spoilers.";
        }
        if (strtolower($contextTagValue) === 'short') {
            return "Mock short bio for '{$this->slug}'.";
        }

        return "This is a mock description for the TV show '{$this->slug}'. ".
            'It is automatically generated without calling the real OpenAI API.';
    }
}
