<?php

namespace App\Actions;

use App\Enums\ContextTag;
use App\Enums\Locale;
use App\Events\MovieGenerationRequested;
use App\Models\Movie;
use App\Services\JobStatusService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueMovieGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(
        string $slug,
        ?float $confidence = null,
        ?Movie $existingMovie = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $tmdbData = null
    ): array {
        Log::info(__METHOD__, [
            'slug' => $slug,
            'locale' => $locale,
            'context_tag' => $contextTag,
            'existing_movie' => $existingMovie,
        ]);
        $normalizedLocale = $this->normalizeLocale($locale) ?? Locale::EN_US->value;
        $normalizedContextTag = $this->normalizeContextTag($contextTag);

        $existingMovie ??= Movie::where('slug', $slug)->first();

        $existingJob = $this->jobStatusService->findActiveJobForSlug('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
        Log::info('QueueMovieGenerationAction: lookup result', [
            'slug' => $slug,
            'locale' => $normalizedLocale,
            'context_tag' => $normalizedContextTag,
            'existing_job_found' => $existingJob !== null,
        ]);
        if ($existingJob) {
            Log::info('QueueMovieGenerationAction: reusing existing job', [
                'slug' => $slug,
                'locale' => $normalizedLocale,
                'context_tag' => $normalizedContextTag,
                'existing_job' => $existingJob,
            ]);

            return $this->buildExistingJobResponse($slug, $existingJob, $existingMovie, $normalizedLocale, $normalizedContextTag);
        }

        $jobId = (string) Str::uuid();

        if (! $this->jobStatusService->acquireGenerationSlot(
            'MOVIE',
            $slug,
            $jobId,
            $normalizedLocale,
            $normalizedContextTag
        )) {
            $existingJob = $this->jobStatusService->findActiveJobForSlug('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
            if ($existingJob) {
                return $this->buildExistingJobResponse($slug, $existingJob, $existingMovie, $normalizedLocale, $normalizedContextTag);
            }

            $slotJobId = $this->jobStatusService->currentGenerationSlotJobId('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
            if ($slotJobId !== null) {
                return [
                    'job_id' => $slotJobId,
                    'status' => 'PENDING',
                    'message' => 'Generation already queued for movie slug',
                    'slug' => $slug,
                    'confidence' => $confidence,
                    'confidence_level' => $this->confidenceLabel($confidence),
                    'locale' => $normalizedLocale,
                    'context_tag' => $normalizedContextTag,
                ];
            }

            // Slot was stale, try to acquire again.
            $this->jobStatusService->releaseGenerationSlot('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
            if (! $this->jobStatusService->acquireGenerationSlot('MOVIE', $slug, $jobId, $normalizedLocale, $normalizedContextTag)) {
                // After releasing stale slot, check again for existing job or slot holder
                $existingJob = $this->jobStatusService->findActiveJobForSlug('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
                if ($existingJob) {
                    return $this->buildExistingJobResponse($slug, $existingJob, $existingMovie, $normalizedLocale, $normalizedContextTag);
                }

                $slotJobId = $this->jobStatusService->currentGenerationSlotJobId('MOVIE', $slug, $normalizedLocale, $normalizedContextTag);
                if ($slotJobId !== null) {
                    return [
                        'job_id' => $slotJobId,
                        'status' => 'PENDING',
                        'message' => 'Generation already queued for movie slug',
                        'slug' => $slug,
                        'confidence' => $confidence,
                        'confidence_level' => $this->confidenceLabel($confidence),
                        'locale' => $normalizedLocale,
                        'context_tag' => $normalizedContextTag,
                    ];
                }

                // Fallback: Even if slot acquisition failed, we should still initialize the job
                // to ensure job_id is always present in the response (API contract requirement)
                // This handles the rare edge case where slot management is in an inconsistent state
                Log::warning('QueueMovieGenerationAction: slot acquisition failed after retry, initializing job anyway', [
                    'slug' => $slug,
                    'job_id' => $jobId,
                    'locale' => $normalizedLocale,
                    'context_tag' => $normalizedContextTag,
                ]);

                // Initialize job status and dispatch event even though slot acquisition failed
                // This ensures the job_id is always present and the generation can proceed
                $baselineDescriptionId = $existingMovie?->default_description_id;
                $this->jobStatusService->initializeStatus(
                    $jobId,
                    'MOVIE',
                    $slug,
                    $confidence,
                    $normalizedLocale,
                    $normalizedContextTag
                );

                event(new MovieGenerationRequested(
                    $slug,
                    $jobId,
                    existingMovieId: $existingMovie?->id,
                    baselineDescriptionId: $baselineDescriptionId,
                    locale: $normalizedLocale,
                    contextTag: $normalizedContextTag,
                    tmdbData: $tmdbData
                ));

                $response = [
                    'job_id' => $jobId,
                    'status' => 'PENDING',
                    'message' => 'Generation queued for movie by slug',
                    'slug' => $slug,
                    'confidence' => $confidence,
                    'confidence_level' => $this->confidenceLabel($confidence),
                    'locale' => $normalizedLocale,
                ];

                if ($existingMovie) {
                    $response['existing_id'] = $existingMovie->id;
                    $response['description_id'] = $baselineDescriptionId;
                }

                if ($normalizedContextTag !== null) {
                    $response['context_tag'] = $normalizedContextTag;
                }

                return $response;
            }
        }

        $baselineDescriptionId = $existingMovie?->default_description_id;

        $this->jobStatusService->initializeStatus(
            $jobId,
            'MOVIE',
            $slug,
            $confidence,
            $normalizedLocale,
            $normalizedContextTag
        );

        event(new MovieGenerationRequested(
            $slug,
            $jobId,
            existingMovieId: $existingMovie?->id,
            baselineDescriptionId: $baselineDescriptionId,
            locale: $normalizedLocale,
            contextTag: $normalizedContextTag,
            tmdbData: $tmdbData
        ));
        Log::info('QueueMovieGenerationAction: dispatched new job', [
            'job_id' => $jobId,
            'slug' => $slug,
            'locale' => $normalizedLocale,
            'context_tag' => $normalizedContextTag,
        ]);

        $response = [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->confidenceLabel($confidence),
            'locale' => $normalizedLocale,
        ];

        if ($existingMovie) {
            $response['existing_id'] = $existingMovie->id;
            $response['description_id'] = $baselineDescriptionId;
        }

        if ($normalizedContextTag !== null) {
            $response['context_tag'] = $normalizedContextTag;
        }

        return $response;
    }

    /**
     * @param  array{job_id: string, status: string, confidence?: mixed, locale?: string|null, context_tag?: string|null}  $existingJob
     */
    protected function buildExistingJobResponse(
        string $slug,
        array $existingJob,
        ?Movie $existingMovie = null,
        ?string $locale = null,
        ?string $contextTag = null
    ): array {
        $confidence = $existingJob['confidence'] ?? null;
        $status = (string) $existingJob['status'];
        $response = [
            'job_id' => $existingJob['job_id'],
            'status' => $status,
            'message' => 'Generation already queued for movie slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->confidenceLabel(is_numeric($confidence) ? (float) $confidence : null),
            'locale' => $existingJob['locale'] ?? $locale ?? Locale::EN_US->value,
        ];

        if ($existingMovie) {
            $response['existing_id'] = $existingMovie->id;
            $response['description_id'] = $existingMovie->default_description_id;
        }

        if (array_key_exists('context_tag', $existingJob) && $existingJob['context_tag'] !== null) {
            $response['context_tag'] = $existingJob['context_tag'];
        } elseif ($contextTag !== null) {
            $response['context_tag'] = $contextTag;
        }

        return $response;
    }

    private function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }

        $candidate = str_replace('_', '-', $locale);
        $candidateLower = strtolower($candidate);

        foreach (Locale::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function normalizeContextTag(?string $contextTag): ?string
    {
        if ($contextTag === null || $contextTag === '') {
            return null;
        }

        $candidateLower = strtolower($contextTag);

        foreach (ContextTag::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }
}
