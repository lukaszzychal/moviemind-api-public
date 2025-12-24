<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\AiOutputValidator;
use App\Services\JobErrorFormatter;
use App\Services\JobStatusService;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Real Generate TV Series Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGenerateTvSeriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingTvSeriesId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    /**
     * Execute the job.
     * Note: OpenAiClientInterface is injected via method injection.
     */
    public function handle(OpenAiClientInterface $openAiClient): void
    {
        Log::info('RealGenerateTvSeriesJob started', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'existing_tv_series_id' => $this->existingTvSeriesId,
            'baseline_description_id' => $this->baselineDescriptionId,
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
            'tmdb_data' => $this->tmdbData !== null,
            'pid' => getmypid(),
        ]);
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);

        try {
            $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);

            if ($existing) {
                $this->refreshExistingTvSeries($existing, $openAiClient);
                Log::info('RealGenerateTvSeriesJob refreshed existing TV series', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'tv_series_id' => $existing->id,
                ]);

                return;
            }

            try {
                $result = $this->createTvSeriesRecord($openAiClient);

                if ($result === null) {
                    return;
                }

                [$tvSeries, $description, $localeValue, $contextTag] = $result;
                $this->promoteDefaultIfEligible($tvSeries, $description);
                $this->invalidateTvSeriesCaches($tvSeries);
                $this->updateCache('DONE', $tvSeries->id, $tvSeries->slug, $description->id, $localeValue, $contextTag);
                Log::info('RealGenerateTvSeriesJob created new TV series', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'tv_series_id' => $tvSeries->id,
                    'description_id' => $description->id,
                    'locale' => $localeValue,
                    'context_tag' => $contextTag,
                    'pid' => getmypid(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueTvSeriesSlugViolation($exception)) {
                    $existingAfterViolation = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);
                    if ($existingAfterViolation) {
                        Log::info('RealGenerateTvSeriesJob detected concurrent creation - using existing TV series', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'tv_series_id' => $existingAfterViolation->id,
                            'pid' => getmypid(),
                        ]);
                        $this->markDoneUsingExisting($existingAfterViolation);

                        return;
                    }
                }

                throw $exception;
            }
        } catch (\Throwable $e) {
            Log::error('RealGenerateTvSeriesJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'TV_SERIES');
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        } finally {
            $jobStatusService->releaseGenerationSlot('TV_SERIES', $this->slug, $this->locale, $this->contextTag);
        }
    }

    /**
     * Create TV series record with AI-generated description.
     *
     * @return array{0: TvSeries, 1: TvSeriesDescription, 2: string, 3: string}|null
     */
    private function createTvSeriesRecord(OpenAiClientInterface $openAiClient): ?array
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);

        try {
            return $lock->block(5, function () use ($openAiClient, $tvSeriesRepository): ?array {
                // Double-check after acquiring lock
                $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);
                if ($existing) {
                    $this->refreshExistingTvSeries($existing, $openAiClient);

                    return null;
                }

                // Call AI to generate TV series data
                $aiResponse = $openAiClient->generateTvSeries($this->slug, $this->tmdbData);

                if ($aiResponse['success'] === false) {
                    $error = $aiResponse['error'] ?? 'Unknown error';
                    throw new \RuntimeException('AI API returned error: '.$error);
                }

                // Validate AI response
                $descriptionText = $aiResponse['description'] ?? null;
                if (! is_string($descriptionText) || trim($descriptionText) === '') {
                    throw new \RuntimeException('AI response missing required description field');
                }

                // Sanitize and validate description
                /** @var AiOutputValidator $outputValidator */
                $outputValidator = app(AiOutputValidator::class);
                $validation = $outputValidator->validateAndSanitizeDescription($descriptionText, $this->tmdbData);

                if (! $validation['valid']) {
                    throw new \RuntimeException(
                        'AI output validation failed: '.implode('; ', $validation['errors'])
                    );
                }

                $descriptionText = $validation['sanitized'];

                // Parse slug to extract title and year
                $parsed = TvSeries::parseSlug($this->slug);
                $title = $aiResponse['title'] ?? ($parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title());
                $firstAirYear = $aiResponse['first_air_year'] ?? $parsed['year'] ?? (int) date('Y');

                // Generate unique slug
                $generatedSlug = TvSeries::generateSlug((string) $title, $firstAirYear);

                // Create TV series
                $tvSeries = TvSeries::create([
                    'title' => (string) $title,
                    'slug' => $generatedSlug,
                    'first_air_date' => "{$firstAirYear}-01-01",
                    'number_of_seasons' => null,
                    'number_of_episodes' => null,
                    'genres' => $aiResponse['genres'] ?? ['Drama'],
                ]);

                $locale = $this->resolveLocale();
                $contextTag = $this->determineContextTag($tvSeries, $locale);

                $description = $this->persistDescription($tvSeries, $locale, $contextTag, [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]);

                return [$tvSeries->fresh(['descriptions']), $description, $locale->value, $contextTag];
            });
        } catch (LockTimeoutException $exception) {
            /** @var TvSeriesRepository $tvSeriesRepository */
            $tvSeriesRepository = app(TvSeriesRepository::class);
            $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);
            if ($existing) {
                $this->refreshExistingTvSeries($existing, $openAiClient);

                return null;
            }

            throw $exception;
        }
    }

    private function refreshExistingTvSeries(TvSeries $tvSeries, OpenAiClientInterface $openAiClient): void
    {
        $tvSeries->loadMissing('descriptions');

        // Call AI to generate description
        $aiResponse = $openAiClient->generateTvSeries($this->slug, $this->tmdbData);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';
            throw new \RuntimeException('AI API returned error: '.$error);
        }

        $descriptionText = $aiResponse['description'] ?? null;
        if (! is_string($descriptionText) || trim($descriptionText) === '') {
            throw new \RuntimeException('AI response missing required description field');
        }

        // Sanitize and validate description
        /** @var AiOutputValidator $outputValidator */
        $outputValidator = app(AiOutputValidator::class);
        $validation = $outputValidator->validateAndSanitizeDescription($descriptionText, $this->tmdbData);

        if (! $validation['valid']) {
            throw new \RuntimeException(
                'AI output validation failed: '.implode('; ', $validation['errors'])
            );
        }

        $descriptionText = $validation['sanitized'];

        $locale = $this->resolveLocale();
        $willUpdateBaseline = $this->shouldUpdateBaseline($tvSeries, $locale);

        $description = $willUpdateBaseline
            ? $this->updateBaselineDescription($tvSeries, $locale, [
                'text' => (string) $descriptionText,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistDescription(
                $tvSeries,
                $locale,
                $this->determineContextTag($tvSeries, $locale),
                [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        $this->promoteDefaultIfEligible($tvSeries, $description);
        $this->invalidateTvSeriesCaches($tvSeries);
        $contextForCache = $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag;
        $this->updateCache('DONE', $tvSeries->id, $tvSeries->slug, $description->id, $locale->value, $contextForCache);
    }

    private function updateCache(
        string $status,
        ?string $id = null,
        ?string $slug = null,
        ?string $descriptionId = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $error = null
    ): void {
        $payload = [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'TV_SERIES',
            'slug' => $slug ?? $this->slug,
            'requested_slug' => $this->slug,
            'id' => $id,
            'description_id' => $descriptionId,
            'locale' => $locale ?? $this->locale,
            'context_tag' => $contextTag ?? $this->contextTag,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        Cache::put($this->cacheKey(), $payload, now()->addMinutes(15));
    }

    private function resolveLocale(): Locale
    {
        if ($this->locale) {
            $normalized = $this->normalizeLocale($this->locale);
            if ($normalized !== null && ($enum = Locale::tryFrom($normalized))) {
                return $enum;
            }
        }

        return Locale::EN_US;
    }

    private function normalizeLocale(string $locale): ?string
    {
        $candidate = str_replace('_', '-', $locale);
        $candidateLower = strtolower($candidate);

        foreach (Locale::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function determineContextTag(TvSeries $tvSeries, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($tvSeries);
    }

    private function normalizeContextTag(string $contextTag): ?string
    {
        $candidateLower = strtolower($contextTag);

        foreach (ContextTag::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function persistDescription(TvSeries $tvSeries, Locale $locale, string $contextTag, array $attributes): TvSeriesDescription
    {
        $existing = TvSeriesDescription::where('tv_series_id', $tvSeries->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return TvSeriesDescription::create(array_merge([
            'tv_series_id' => $tvSeries->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(TvSeries $tvSeries, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineDescriptionId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineDescription($tvSeries);

        if (! $baseline instanceof TvSeriesDescription) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function getBaselineDescription(TvSeries $tvSeries): ?TvSeriesDescription
    {
        $description = $tvSeries->descriptions->firstWhere('id', $this->baselineDescriptionId);

        return $description instanceof TvSeriesDescription
            ? $description
            : TvSeriesDescription::find($this->baselineDescriptionId);
    }

    private function updateBaselineDescription(TvSeries $tvSeries, Locale $locale, array $attributes): TvSeriesDescription
    {
        $baseline = $this->getBaselineDescription($tvSeries);

        if (! $baseline instanceof TvSeriesDescription) {
            return $this->persistDescription($tvSeries, $locale, $this->determineContextTag($tvSeries, $locale), $attributes);
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    private function nextContextTag(TvSeries $tvSeries): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $tvSeries->descriptions()->pluck('context_tag')->all()
        );
        $preferredOrder = [
            ContextTag::DEFAULT->value,
            ContextTag::MODERN->value,
            ContextTag::CRITICAL->value,
            ContextTag::HUMOROUS->value,
        ];

        foreach ($preferredOrder as $candidate) {
            if (! in_array($candidate, $existingTags, true)) {
                return $candidate;
            }
        }

        return ContextTag::DEFAULT->value;
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    public function failed(\Throwable $exception): void
    {
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        $jobStatusService->releaseGenerationSlot('TV_SERIES', $this->slug, $this->locale, $this->contextTag);

        Log::error('RealGenerateTvSeriesJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, 'TV_SERIES');
        $this->updateCache('FAILED', error: $errorData);
    }

    private function promoteDefaultIfEligible(TvSeries $tvSeries, TvSeriesDescription $description): void
    {
        $lock = Cache::lock($this->defaultLockKey($tvSeries), 10);

        try {
            $lock->block(5, function () use ($tvSeries, $description): void {
                $tvSeries->refresh();
                $currentDefault = $tvSeries->default_description_id;

                if ($this->baselineDescriptionId !== null) {
                    if ((string) $currentDefault !== (string) $this->baselineDescriptionId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $tvSeries->default_description_id = $description->id;
                $tvSeries->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('RealGenerateTvSeriesJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'tv_series_id' => $tvSeries->id,
            ]);
        }
    }

    private function creationLockKey(): string
    {
        return 'lock:tv_series:create:'.$this->slug;
    }

    private function defaultLockKey(TvSeries $tvSeries): string
    {
        return 'lock:tv_series:default:'.$tvSeries->id;
    }

    private function invalidateTvSeriesCaches(TvSeries $tvSeries): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $tvSeries->slug,
        ]));

        $descriptionIds = $tvSeries->descriptions()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('tv_series:'.$slug.':desc:default');

            foreach ($descriptionIds as $descriptionId) {
                Cache::forget('tv_series:'.$slug.':desc:'.$descriptionId);
            }
        }
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    private function isUniqueTvSeriesSlugViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'unique') && str_contains($message, 'slug');
    }

    private function markDoneUsingExisting(TvSeries $tvSeries): void
    {
        $tvSeries->loadMissing('descriptions');
        /** @var TvSeriesDescription|null $defaultDescription */
        $defaultDescription = $tvSeries->defaultDescription;

        $descriptionId = $defaultDescription instanceof TvSeriesDescription ? (string) $defaultDescription->id : null;
        $contextTagValue = $defaultDescription instanceof TvSeriesDescription
            ? ($defaultDescription->context_tag instanceof ContextTag ? $defaultDescription->context_tag->value : (string) $defaultDescription->context_tag)
            : null;

        $this->updateCache(
            'DONE',
            (string) $tvSeries->id,
            $tvSeries->slug,
            $descriptionId,
            $this->locale,
            $contextTagValue
        );
    }
}
