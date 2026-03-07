<?php

declare(strict_types=1);

namespace App\Jobs\Base;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Jobs\Concerns\ResolvesLocaleAndContext;
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
 * Base abstract class for generating real TV content (TV Series and TV Shows)
 * and extracting AI generated descriptions.
 */
abstract class AbstractRealGenerateTvContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesLocaleAndContext, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingEntityId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    /**
     * E.g. 'TV_SERIES' or 'TV_SHOW'
     */
    abstract protected function entityType(): string;

    /**
     * Return class name of the entity, e.g. TvSeries::class
     */
    abstract protected function modelClass(): string;

    /**
     * Return class name of the description entity, e.g. TvSeriesDescription::class
     */
    abstract protected function descriptionModelClass(): string;

    /**
     * Find entity by slug for job, handled by specific repository
     */
    abstract protected function findExistingBySlug(string $slug, ?string $existingId): ?object;

    /**
     * Call AI API, e.g. `$openAiClient->generateTvSeries(...)`
     */
    abstract protected function callAiApi(OpenAiClientInterface $openAiClient, string $slug, ?array $tmdbData, string $locale, string $contextTag): array;

    /**
     * Foreign key for the entity on the description model, e.g. 'tv_series_id'
     */
    abstract protected function descriptionForeignKey(): string;

    /**
     * Prefix for lock cache, e.g. 'tv_series'
     */
    abstract protected function cachePrefix(): string;

    public function handle(OpenAiClientInterface $openAiClient): void
    {
        Log::info("RealGenerate{$this->entityType()}Job started", [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'existing_entity_id' => $this->existingEntityId,
            'baseline_description_id' => $this->baselineDescriptionId,
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
            'tmdb_data' => $this->tmdbData !== null,
            'pid' => getmypid(),
        ]);
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);

        try {
            $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);

            if ($existing) {
                $this->refreshExistingContent($existing, $openAiClient);
                Log::info("RealGenerate{$this->entityType()}Job refreshed existing content", [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'entity_id' => $existing->id,
                ]);

                return;
            }

            try {
                $result = $this->createContentRecord($openAiClient);

                if ($result === null) {
                    return;
                }

                [$entity, $description, $localeValue, $contextTag] = $result;
                $this->promoteDefaultIfEligible($entity, $description);
                $this->invalidateCaches($entity);
                $this->updateCache('DONE', $entity->id, $entity->slug, $description->id, $localeValue, $contextTag);

                Log::info("RealGenerate{$this->entityType()}Job created new content", [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'entity_id' => $entity->id,
                    'description_id' => $description->id,
                    'locale' => $localeValue,
                    'context_tag' => $contextTag,
                    'pid' => getmypid(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueSlugViolation($exception)) {
                    $existingAfterViolation = $this->findExistingBySlug($this->slug, $this->existingEntityId);
                    if ($existingAfterViolation) {
                        Log::info("RealGenerate{$this->entityType()}Job detected concurrent creation - using existing content", [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'entity_id' => $existingAfterViolation->id,
                            'pid' => getmypid(),
                        ]);
                        $this->markDoneUsingExisting($existingAfterViolation);

                        return;
                    }
                }

                throw $exception;
            }
        } catch (\Throwable $e) {
            Log::error("RealGenerate{$this->entityType()}Job failed", [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, $this->entityType());
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        } finally {
            $jobStatusService->releaseGenerationSlot($this->entityType(), $this->slug, $this->locale, $this->contextTag);
        }
    }

    /**
     * @return array{0: object, 1: object, 2: string, 3: string}|null
     */
    private function createContentRecord(OpenAiClientInterface $openAiClient): ?array
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        try {
            return $lock->block(5, function () use ($openAiClient): ?array {
                $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);
                if ($existing) {
                    $this->refreshExistingContent($existing, $openAiClient);

                    return null;
                }

                $locale = $this->resolveLocale();
                $contextTagForPrompt = $this->contextTag !== null ? ($this->normalizeContextTag($this->contextTag) ?? 'default') : 'default';

                $aiResponse = $this->callAiApi($openAiClient, $this->slug, $this->tmdbData, $locale->value, $contextTagForPrompt);

                if (isset($aiResponse['success']) && $aiResponse['success'] === false) {
                    $error = $aiResponse['error'] ?? 'Unknown error';
                    throw new \RuntimeException('AI API returned error: '.$error);
                }

                $descriptionText = $aiResponse['description'] ?? null;
                if (! is_string($descriptionText) || trim($descriptionText) === '') {
                    throw new \RuntimeException('AI response missing required description field');
                }

                /** @var AiOutputValidator $outputValidator */
                $outputValidator = app(AiOutputValidator::class);
                $validation = $outputValidator->validateAndSanitizeDescription($descriptionText, $this->tmdbData);

                if (! $validation['valid']) {
                    throw new \RuntimeException(
                        'AI output validation failed: '.implode('; ', $validation['errors'])
                    );
                }

                $descriptionText = $validation['sanitized'];

                /** @var class-string $modelClass */
                $modelClass = $this->modelClass();
                /** @var callable $parseCallable */
                $parseCallable = [$modelClass, 'parseSlug'];
                /** @var callable $generateSlugCallable */
                $generateSlugCallable = [$modelClass, 'generateSlug'];

                $parsed = $parseCallable($this->slug);
                $title = $aiResponse['title'] ?? ($parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title());
                $firstAirYear = $aiResponse['first_air_year'] ?? $parsed['year'] ?? (int) date('Y');

                // @phpstan-ignore-next-line
                $tmdbId = $this->tmdbData['id'] ?? ($aiResponse['tmdb_id'] ?? null);

                $generatedSlug = $generateSlugCallable((string) $title, $firstAirYear);

                $entity = null;

                // SMART DEDUPLICATION STRATEGY
                if (! empty($tmdbId)) {
                    $existingByTmdb = $modelClass::where('tmdb_id', $tmdbId)->first();
                    if ($existingByTmdb) {
                        $entity = $existingByTmdb;
                        Log::info("RealGenerate{$this->entityType()}Job deduplicated by TMDb ID", [
                            'slug' => $this->slug,
                            'generated_slug' => $generatedSlug,
                            'tmdb_id' => $tmdbId,
                            'found_id' => $entity->id,
                        ]);
                    }
                }

                if (! $entity) {
                    $existingByGeneratedSlug = $this->findExistingBySlug($generatedSlug, null);
                    if ($existingByGeneratedSlug) {
                        $entity = $existingByGeneratedSlug;
                        Log::info("RealGenerate{$this->entityType()}Job deduplicated by Slug", [
                            'slug' => $this->slug,
                            'generated_slug' => $generatedSlug,
                            'found_id' => $entity->id,
                        ]);
                    }
                }

                if (! $entity) {
                    $candidates = $modelClass::where('title', (string) $title)->get();

                    foreach ($candidates as $candidate) {
                        $candidateYear = $candidate->first_air_date ? (int) substr($candidate->first_air_date->format('Y-m-d'), 0, 4) : null;

                        if ($candidateYear === (int) $firstAirYear) {
                            $entity = $candidate;
                            Log::info("RealGenerate{$this->entityType()}Job deduplicated by Title + Year", [
                                'title' => $title,
                                'year' => $firstAirYear,
                                'found_id' => $entity->id,
                            ]);
                            break;
                        }

                        if ($candidate->first_air_date === null) {
                            $entity = $candidate;
                            Log::info("RealGenerate{$this->entityType()}Job deduplicated by Title (Legacy/Null Year)", [
                                'title' => $title,
                                'found_id' => $entity->id,
                            ]);
                            break;
                        }
                    }
                }

                if ($entity) {
                    $updates = [];
                    if (! empty($tmdbId) && ! $entity->tmdb_id) {
                        $updates['tmdb_id'] = $tmdbId;
                    }
                    if (! $entity->first_air_date && $firstAirYear) {
                        $updates['first_air_date'] = "{$firstAirYear}-01-01";
                    }

                    if (! empty($updates)) {
                        $entity->update($updates);
                        Log::info("RealGenerate{$this->entityType()}Job updated existing content metadata", [
                            'id' => $entity->id,
                            'updates' => $updates,
                        ]);
                    }
                } else {
                    $entity = $modelClass::create([
                        'title' => (string) $title,
                        'slug' => $generatedSlug,
                        'first_air_date' => "{$firstAirYear}-01-01",
                        'number_of_seasons' => null,
                        'number_of_episodes' => null,
                        'genres' => $aiResponse['genres'] ?? ['Drama'],
                        'tmdb_id' => $tmdbId,
                    ]);
                }

                $contextTag = $this->determineContextTag($entity, $locale);

                $description = $this->persistDescription($entity, $locale, $contextTag, [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]);

                return [$entity->fresh(['descriptions']), $description, $locale->value, $contextTag];
            });
        } catch (LockTimeoutException $exception) {
            $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);
            if ($existing) {
                $this->refreshExistingContent($existing, $openAiClient);

                return null;
            }

            throw $exception;
        }
    }

    private function refreshExistingContent(object $entity, OpenAiClientInterface $openAiClient): void
    {
        $entity->loadMissing('descriptions');

        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($entity, $locale);

        $aiResponse = $this->callAiApi($openAiClient, $this->slug, $this->tmdbData, $locale->value, $contextTag);

        if (isset($aiResponse['success']) && $aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';
            throw new \RuntimeException('AI API returned error: '.$error);
        }

        $descriptionText = $aiResponse['description'] ?? null;
        if (! is_string($descriptionText) || trim($descriptionText) === '') {
            throw new \RuntimeException('AI response missing required description field');
        }

        /** @var AiOutputValidator $outputValidator */
        $outputValidator = app(AiOutputValidator::class);
        $validation = $outputValidator->validateAndSanitizeDescription($descriptionText, $this->tmdbData);

        if (! $validation['valid']) {
            throw new \RuntimeException(
                'AI output validation failed: '.implode('; ', $validation['errors'])
            );
        }

        $descriptionText = $validation['sanitized'];
        $willUpdateBaseline = $this->shouldUpdateBaseline($entity, $locale);

        $description = $willUpdateBaseline
            ? $this->updateBaselineDescription($entity, $locale, [
                'text' => (string) $descriptionText,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistDescription(
                $entity,
                $locale,
                $contextTag,
                [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        $this->promoteDefaultIfEligible($entity, $description);
        $this->invalidateCaches($entity);
        $contextForCache = ($description->context_tag instanceof ContextTag ? $description->context_tag->value : null) ?? (string) $description->context_tag;
        $this->updateCache('DONE', $entity->id, $entity->slug, $description->id, $locale->value, $contextForCache);
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
            'entity' => $this->entityType(),
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

    protected function getExistingContextTags(object $entity): array
    {
        return $entity->descriptions()->pluck('context_tag')->all();
    }

    private function persistDescription(object $entity, Locale $locale, string $contextTag, array $attributes): object
    {
        /** @var class-string $descriptionClass */
        $descriptionClass = $this->descriptionModelClass();
        $foreignKey = $this->descriptionForeignKey();

        $existing = $descriptionClass::where($foreignKey, $entity->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return $descriptionClass::create(array_merge([
            $foreignKey => $entity->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(object $entity, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineDescriptionId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineDescription($entity);
        $descriptionClass = ltrim($this->descriptionModelClass(), '\\');

        if (! $baseline || get_class($baseline) !== $descriptionClass) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function getBaselineDescription(object $entity): ?object
    {
        $descriptionClass = $this->descriptionModelClass();
        $description = $entity->descriptions->firstWhere('id', $this->baselineDescriptionId);

        return $description instanceof $descriptionClass
            ? $description
            : $descriptionClass::find($this->baselineDescriptionId);
    }

    private function updateBaselineDescription(object $entity, Locale $locale, array $attributes): object
    {
        $baseline = $this->getBaselineDescription($entity);
        $descriptionClass = ltrim($this->descriptionModelClass(), '\\');

        if (! $baseline || get_class($baseline) !== $descriptionClass) {
            return $this->persistDescription($entity, $locale, $this->determineContextTag($entity, $locale), $attributes);
        }

        if ($this->contextTag !== null) {
            $normalizedContextTag = $this->normalizeContextTag($this->contextTag);
            $baselineContextValue = $baseline->context_tag instanceof ContextTag
                ? $baseline->context_tag->value
                : (string) $baseline->context_tag;

            if ($normalizedContextTag !== null && $baselineContextValue !== $normalizedContextTag) {
                return $this->persistDescription($entity, $locale, $normalizedContextTag, $attributes);
            }
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    public function failed(\Throwable $exception): void
    {
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        $jobStatusService->releaseGenerationSlot($this->entityType(), $this->slug, $this->locale, $this->contextTag);

        Log::error("RealGenerate{$this->entityType()}Job permanently failed", [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, $this->entityType());
        $this->updateCache('FAILED', error: $errorData);
    }

    private function promoteDefaultIfEligible(object $entity, object $description): void
    {
        $lock = Cache::lock($this->defaultLockKey($entity), 10);

        try {
            $lock->block(5, function () use ($entity, $description): void {
                $entity->refresh();
                $currentDefault = $entity->default_description_id;

                if ($this->baselineDescriptionId !== null) {
                    if ((string) $currentDefault !== (string) $this->baselineDescriptionId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $entity->default_description_id = $description->id;
                $entity->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning("RealGenerate{$this->entityType()}Job default promotion lock timeout", [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'entity_id' => $entity->id,
            ]);
        }
    }

    private function creationLockKey(): string
    {
        return "lock:{$this->cachePrefix()}:create:{$this->slug}";
    }

    private function defaultLockKey(object $entity): string
    {
        return "lock:{$this->cachePrefix()}:default:{$entity->id}";
    }

    private function invalidateCaches(object $entity): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $entity->slug,
        ]));

        $descriptionIds = $entity->descriptions()->pluck('id')->all();
        $prefix = $this->cachePrefix();

        foreach ($slugs as $slug) {
            Cache::forget("{$prefix}:{$slug}:desc:default");

            foreach ($descriptionIds as $descriptionId) {
                Cache::forget("{$prefix}:{$slug}:desc:{$descriptionId}");
            }
        }
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    private function isUniqueSlugViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'unique') && str_contains($message, 'slug');
    }

    private function markDoneUsingExisting(object $entity): void
    {
        $entity->loadMissing('descriptions');
        $defaultDescription = $entity->defaultDescription;

        $descriptionClass = ltrim($this->descriptionModelClass(), '\\');

        $descriptionId = ($defaultDescription && get_class($defaultDescription) === $descriptionClass) ? (string) $defaultDescription->id : null;
        $contextTagValue = ($defaultDescription && get_class($defaultDescription) === $descriptionClass)
            ? ($defaultDescription->context_tag instanceof ContextTag ? $defaultDescription->context_tag->value : (string) $defaultDescription->context_tag)
            : null;

        $this->updateCache(
            'DONE',
            (string) $entity->id,
            $entity->slug,
            $descriptionId,
            $this->locale,
            $contextTagValue
        );
    }
}
