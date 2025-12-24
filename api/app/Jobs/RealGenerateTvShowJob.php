<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
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
 * Real Generate TV Show Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGenerateTvShowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingTvShowId = null,
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
        Log::info('RealGenerateTvShowJob started', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'existing_tv_show_id' => $this->existingTvShowId,
            'baseline_description_id' => $this->baselineDescriptionId,
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
            'tmdb_data' => $this->tmdbData !== null,
            'pid' => getmypid(),
        ]);
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        /** @var TvShowRepository $tvShowRepository */
        $tvShowRepository = app(TvShowRepository::class);

        try {
            $existing = $tvShowRepository->findBySlugForJob($this->slug, $this->existingTvShowId);

            if ($existing) {
                $this->refreshExistingTvShow($existing, $openAiClient);
                Log::info('RealGenerateTvShowJob refreshed existing TV show', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'tv_show_id' => $existing->id,
                ]);

                return;
            }

            try {
                $result = $this->createTvShowRecord($openAiClient);

                if ($result === null) {
                    return;
                }

                [$tvShow, $description, $localeValue, $contextTag] = $result;
                $this->promoteDefaultIfEligible($tvShow, $description);
                $this->invalidateTvShowCaches($tvShow);
                $this->updateCache('DONE', $tvShow->id, $tvShow->slug, $description->id, $localeValue, $contextTag);
                Log::info('RealGenerateTvShowJob created new TV show', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'tv_show_id' => $tvShow->id,
                    'description_id' => $description->id,
                    'locale' => $localeValue,
                    'context_tag' => $contextTag,
                    'pid' => getmypid(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueTvShowSlugViolation($exception)) {
                    $existingAfterViolation = $tvShowRepository->findBySlugForJob($this->slug, $this->existingTvShowId);
                    if ($existingAfterViolation) {
                        Log::info('RealGenerateTvShowJob detected concurrent creation - using existing TV show', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'tv_show_id' => $existingAfterViolation->id,
                            'pid' => getmypid(),
                        ]);
                        $this->markDoneUsingExisting($existingAfterViolation);

                        return;
                    }
                }

                throw $exception;
            }
        } catch (\Throwable $e) {
            Log::error('RealGenerateTvShowJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'TV_SHOW');
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        } finally {
            $jobStatusService->releaseGenerationSlot('TV_SHOW', $this->slug, $this->locale, $this->contextTag);
        }
    }

    /**
     * Create TV show record with AI-generated description.
     *
     * @return array{0: TvShow, 1: TvShowDescription, 2: string, 3: string}|null
     */
    private function createTvShowRecord(OpenAiClientInterface $openAiClient): ?array
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        /** @var TvShowRepository $tvShowRepository */
        $tvShowRepository = app(TvShowRepository::class);

        try {
            return $lock->block(5, function () use ($openAiClient, $tvShowRepository): ?array {
                // Double-check after acquiring lock
                $existing = $tvShowRepository->findBySlugForJob($this->slug, $this->existingTvShowId);
                if ($existing) {
                    $this->refreshExistingTvShow($existing, $openAiClient);

                    return null;
                }

                // Call AI to generate TV show data
                $aiResponse = $openAiClient->generateTvShow($this->slug, $this->tmdbData);

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
                $parsed = TvShow::parseSlug($this->slug);
                $title = $aiResponse['title'] ?? ($parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title());
                $firstAirYear = $aiResponse['first_air_year'] ?? $parsed['year'] ?? (int) date('Y');

                // Generate unique slug
                $generatedSlug = TvShow::generateSlug((string) $title, $firstAirYear);

                // Create TV show
                $tvShow = TvShow::create([
                    'title' => (string) $title,
                    'slug' => $generatedSlug,
                    'first_air_date' => "{$firstAirYear}-01-01",
                    'number_of_seasons' => null,
                    'number_of_episodes' => null,
                    'genres' => $aiResponse['genres'] ?? ['Talk'],
                    'show_type' => $aiResponse['show_type'] ?? 'TALK_SHOW',
                ]);

                $locale = $this->resolveLocale();
                $contextTag = $this->determineContextTag($tvShow, $locale);

                $description = $this->persistDescription($tvShow, $locale, $contextTag, [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]);

                return [$tvShow->fresh(['descriptions']), $description, $locale->value, $contextTag];
            });
        } catch (LockTimeoutException $exception) {
            /** @var TvShowRepository $tvShowRepository */
            $tvShowRepository = app(TvShowRepository::class);
            $existing = $tvShowRepository->findBySlugForJob($this->slug, $this->existingTvShowId);
            if ($existing) {
                $this->refreshExistingTvShow($existing, $openAiClient);

                return null;
            }

            throw $exception;
        }
    }

    private function refreshExistingTvShow(TvShow $tvShow, OpenAiClientInterface $openAiClient): void
    {
        $tvShow->loadMissing('descriptions');

        // Call AI to generate description
        $aiResponse = $openAiClient->generateTvShow($this->slug, $this->tmdbData);

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
        $willUpdateBaseline = $this->shouldUpdateBaseline($tvShow, $locale);

        $description = $willUpdateBaseline
            ? $this->updateBaselineDescription($tvShow, $locale, [
                'text' => (string) $descriptionText,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistDescription(
                $tvShow,
                $locale,
                $this->determineContextTag($tvShow, $locale),
                [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        $this->promoteDefaultIfEligible($tvShow, $description);
        $this->invalidateTvShowCaches($tvShow);
        $contextForCache = $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag;
        $this->updateCache('DONE', $tvShow->id, $tvShow->slug, $description->id, $locale->value, $contextForCache);
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
            'entity' => 'TV_SHOW',
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

    private function determineContextTag(TvShow $tvShow, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($tvShow);
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

    private function persistDescription(TvShow $tvShow, Locale $locale, string $contextTag, array $attributes): TvShowDescription
    {
        $existing = TvShowDescription::where('tv_show_id', $tvShow->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return TvShowDescription::create(array_merge([
            'tv_show_id' => $tvShow->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(TvShow $tvShow, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineDescriptionId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineDescription($tvShow);

        if (! $baseline instanceof TvShowDescription) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function getBaselineDescription(TvShow $tvShow): ?TvShowDescription
    {
        $description = $tvShow->descriptions->firstWhere('id', $this->baselineDescriptionId);

        return $description instanceof TvShowDescription
            ? $description
            : TvShowDescription::find($this->baselineDescriptionId);
    }

    private function updateBaselineDescription(TvShow $tvShow, Locale $locale, array $attributes): TvShowDescription
    {
        $baseline = $this->getBaselineDescription($tvShow);

        if (! $baseline instanceof TvShowDescription) {
            return $this->persistDescription($tvShow, $locale, $this->determineContextTag($tvShow, $locale), $attributes);
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    private function nextContextTag(TvShow $tvShow): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $tvShow->descriptions()->pluck('context_tag')->all()
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
        $jobStatusService->releaseGenerationSlot('TV_SHOW', $this->slug, $this->locale, $this->contextTag);

        Log::error('RealGenerateTvShowJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, 'TV_SHOW');
        $this->updateCache('FAILED', error: $errorData);
    }

    private function promoteDefaultIfEligible(TvShow $tvShow, TvShowDescription $description): void
    {
        $lock = Cache::lock($this->defaultLockKey($tvShow), 10);

        try {
            $lock->block(5, function () use ($tvShow, $description): void {
                $tvShow->refresh();
                $currentDefault = $tvShow->default_description_id;

                if ($this->baselineDescriptionId !== null) {
                    if ((string) $currentDefault !== (string) $this->baselineDescriptionId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $tvShow->default_description_id = $description->id;
                $tvShow->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('RealGenerateTvShowJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'tv_show_id' => $tvShow->id,
            ]);
        }
    }

    private function creationLockKey(): string
    {
        return 'lock:tv_show:create:'.$this->slug;
    }

    private function defaultLockKey(TvShow $tvShow): string
    {
        return 'lock:tv_show:default:'.$tvShow->id;
    }

    private function invalidateTvShowCaches(TvShow $tvShow): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $tvShow->slug,
        ]));

        $descriptionIds = $tvShow->descriptions()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('tv_show:'.$slug.':desc:default');

            foreach ($descriptionIds as $descriptionId) {
                Cache::forget('tv_show:'.$slug.':desc:'.$descriptionId);
            }
        }
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    private function isUniqueTvShowSlugViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'unique') && str_contains($message, 'slug');
    }

    private function markDoneUsingExisting(TvShow $tvShow): void
    {
        $tvShow->loadMissing('descriptions');
        /** @var TvShowDescription|null $defaultDescription */
        $defaultDescription = $tvShow->defaultDescription;

        $descriptionId = $defaultDescription instanceof TvShowDescription ? (string) $defaultDescription->id : null;
        $contextTagValue = $defaultDescription instanceof TvShowDescription
            ? ($defaultDescription->context_tag instanceof ContextTag ? $defaultDescription->context_tag->value : (string) $defaultDescription->context_tag)
            : null;

        $this->updateCache(
            'DONE',
            (string) $tvShow->id,
            $tvShow->slug,
            $descriptionId,
            $this->locale,
            $contextTagValue
        );
    }
}
