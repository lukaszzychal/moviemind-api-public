<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContextTag;
use App\Enums\Locale;
use App\Events\TvSeriesGenerationRequested;
use App\Models\TvSeries;
use App\Services\JobStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueTvSeriesGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(
        string $slug,
        ?float $confidence = null,
        ?TvSeries $existingTvSeries = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $tmdbData = null
    ): array {
        Log::info(__METHOD__, [
            'slug' => $slug,
            'locale' => $locale,
            'context_tag' => $contextTag,
            'existing_tv_series_id' => $existingTvSeries?->id,
            'existing_tv_series_slug' => $existingTvSeries?->slug,
        ]);
        $normalizedLocale = $this->normalizeLocale($locale) ?? Locale::EN_US->value;
        $normalizedContextTag = $this->normalizeContextTag($contextTag);

        $existingTvSeries ??= TvSeries::where('slug', $slug)->first();

        $existingJob = $this->jobStatusService->findActiveJobForSlug('TV_SERIES', $slug, $normalizedLocale, $normalizedContextTag);
        if ($existingJob) {
            return $this->buildExistingJobResponse($slug, $existingJob, $existingTvSeries, $normalizedLocale, $normalizedContextTag);
        }

        $jobId = (string) Str::uuid();

        if (! $this->jobStatusService->acquireGenerationSlot(
            'TV_SERIES',
            $slug,
            $jobId,
            $normalizedLocale,
            $normalizedContextTag
        )) {
            $existingJob = $this->jobStatusService->findActiveJobForSlug('TV_SERIES', $slug, $normalizedLocale, $normalizedContextTag);
            if ($existingJob) {
                return $this->buildExistingJobResponse($slug, $existingJob, $existingTvSeries, $normalizedLocale, $normalizedContextTag);
            }

            return [
                'job_id' => $jobId,
                'status' => 'PENDING',
                'message' => 'Generation already queued for TV series slug',
                'slug' => $slug,
                'confidence' => $confidence,
                'confidence_level' => $this->confidenceLabel($confidence),
                'locale' => $normalizedLocale,
                'context_tag' => $normalizedContextTag,
            ];
        }

        $baselineDescriptionId = $existingTvSeries?->default_description_id;
        $this->jobStatusService->initializeStatus($jobId, 'TV_SERIES', $slug, $confidence, $normalizedLocale, $normalizedContextTag);

        // Dispatch event to queue the job
        event(new TvSeriesGenerationRequested(
            $slug,
            $jobId,
            existingTvSeriesId: $existingTvSeries?->id,
            baselineDescriptionId: $baselineDescriptionId,
            locale: $normalizedLocale,
            contextTag: $normalizedContextTag,
            tmdbData: $tmdbData
        ));

        $response = [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for TV series slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->confidenceLabel($confidence),
            'locale' => $normalizedLocale,
            'context_tag' => $normalizedContextTag,
        ];

        if ($existingTvSeries) {
            $response['existing_id'] = $existingTvSeries->id;
        }

        return $response;
    }

    private function buildExistingJobResponse(string $slug, array $existingJob, ?TvSeries $existingTvSeries, string $locale, ?string $contextTag): array
    {
        $response = [
            'job_id' => $existingJob['job_id'],
            'status' => $existingJob['status'],
            'message' => 'Generation already queued for TV series slug',
            'slug' => $slug,
            'confidence' => $existingJob['confidence'] ?? null,
            'confidence_level' => $this->confidenceLabel($existingJob['confidence'] ?? null),
            'locale' => $locale,
            'context_tag' => $contextTag,
        ];

        if ($existingTvSeries) {
            $response['existing_id'] = $existingTvSeries->id;
        }

        return $response;
    }

    /**
     * Convert confidence score to human-readable label.
     */
    private function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        if ($confidence >= 0.9) {
            return 'high';
        }

        if ($confidence >= 0.7) {
            return 'medium';
        }

        if ($confidence >= 0.5) {
            return 'low';
        }

        return 'very_low';
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        try {
            return Locale::from($locale)->value;
        } catch (\ValueError $e) {
            return null;
        }
    }

    private function normalizeContextTag(?string $contextTag): ?string
    {
        if ($contextTag === null || $contextTag === '') {
            return null;
        }

        try {
            return ContextTag::from($contextTag)->value;
        } catch (\ValueError $e) {
            return null;
        }
    }
}
