<?php

declare(strict_types=1);

namespace App\Jobs\Base;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Jobs\Concerns\ResolvesLocaleAndContext;
use App\Services\JobErrorFormatter;
use App\Services\JobStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Base abstract class for generating mock TV content.
 * Used when AI_SERVICE=mock.
 */
abstract class AbstractMockGenerateTvContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ResolvesLocaleAndContext, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingEntityId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    abstract protected function entityType(): string;

    abstract protected function modelClass(): string;

    abstract protected function descriptionModelClass(): string;

    abstract protected function findExistingBySlug(string $slug, ?string $existingId): ?object;

    abstract protected function descriptionForeignKey(): string;

    abstract protected function cachePrefix(): string;

    abstract protected function generateMockDescriptionText(): string;

    public function handle(): void
    {
        try {
            $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);

            if ($existing) {
                $this->refreshExistingContent($existing);

                return;
            }

            $this->createContentWithLock();
        } catch (\Throwable $e) {
            Log::error("MockGenerate{$this->entityType()}Job failed", [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, $this->entityType());
            $this->updateCache('FAILED', error: $errorData);

            throw $e;
        }
    }

    private function createContentWithLock(): void
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        try {
            $lock->block(5, function (): void {
                $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);
                if ($existing) {
                    $this->refreshExistingContent($existing);

                    return;
                }

                [$entity, $description, $localeValue, $contextTag] = $this->createContentRecord();

                $this->promoteDefaultIfEligible($entity, $description);
                $this->invalidateCaches($entity);
                $this->updateCache('DONE', (string) $entity->id, $entity->slug, (string) $description->id, $localeValue, $contextTag);
            });
        } catch (LockTimeoutException $exception) {
            $existing = $this->findExistingBySlug($this->slug, $this->existingEntityId);
            if ($existing) {
                $this->refreshExistingContent($existing);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: object, 1: object, 2: string, 3: string}
     */
    private function createContentRecord(): array
    {
        // Simulate long-running AI generation (mock)
        sleep(3);

        /** @var class-string $modelClass */
        $modelClass = $this->modelClass();
        /** @var callable $parseCallable */
        $parseCallable = [$modelClass, 'parseSlug'];
        /** @var callable $generateSlugCallable */
        $generateSlugCallable = [$modelClass, 'generateSlug'];

        $parsed = $parseCallable($this->slug);
        $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $firstAirYear = $parsed['year'] ?? 2000;

        $generatedSlug = $generateSlugCallable((string) $title, $firstAirYear);

        $existingByGeneratedSlug = $this->findExistingBySlug($generatedSlug, null);

        if ($existingByGeneratedSlug) {
            $entity = $existingByGeneratedSlug;
        } else {
            $existingByTitleYear = $modelClass::where('title', (string) $title)
                ->whereYear('first_air_date', $firstAirYear)
                ->first();

            if ($existingByTitleYear) {
                $entity = $existingByTitleYear;
            } else {
                $entity = $modelClass::create([
                    'title' => (string) $title,
                    'slug' => $generatedSlug,
                    'first_air_date' => "{$firstAirYear}-01-01",
                    'number_of_seasons' => null,
                    'number_of_episodes' => null,
                    'genres' => ['Drama', 'Sci-Fi'],
                ]);
            }
        }

        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($entity, $locale);

        $descriptionText = $this->generateMockDescriptionText();

        $description = $this->persistDescription($entity, $locale, $contextTag, [
            'text' => $descriptionText,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-model',
        ]);

        return [$entity->fresh(['descriptions']), $description, $locale->value, $contextTag];
    }

    private function refreshExistingContent(object $entity): void
    {
        $entity->loadMissing('descriptions');

        sleep(3); // Mock generation delay

        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($entity, $locale);
        $descriptionText = $this->generateMockDescriptionText();

        $description = $this->persistDescription(
            $entity,
            $locale,
            $contextTag,
            [
                'text' => $descriptionText,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-model',
            ]
        );

        $this->promoteDefaultIfEligible($entity, $description);
        $this->invalidateCaches($entity);
        $contextForCache = ($description->context_tag instanceof ContextTag ? $description->context_tag->value : null) ?? (string) $description->context_tag;
        $this->updateCache('DONE', (string) $entity->id, $entity->slug, (string) $description->id, $locale->value, $contextForCache);
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

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    public function failed(\Throwable $exception): void
    {
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        $jobStatusService->releaseGenerationSlot($this->entityType(), $this->slug, $this->locale, $this->contextTag);

        Log::error("MockGenerate{$this->entityType()}Job permanently failed", [
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
            Log::warning("MockGenerate{$this->entityType()}Job default promotion lock timeout", [
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
}
