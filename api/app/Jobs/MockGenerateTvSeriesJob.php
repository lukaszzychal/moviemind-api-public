<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\JobErrorFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Mock Generate TV Series Job - simulates AI generation for development/testing.
 * Used when AI_SERVICE=mock.
 */
class MockGenerateTvSeriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingTvSeriesId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    public function handle(): void
    {
        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);

        try {
            $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);

            if ($existing) {
                $this->refreshExistingTvSeries($existing);

                return;
            }

            $this->createTvSeriesWithLock();
        } catch (\Throwable $e) {
            Log::error('MockGenerateTvSeriesJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'TV_SERIES');
            $this->updateCache('FAILED', error: $errorData);

            throw $e;
        }
    }

    private function createTvSeriesWithLock(): void
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);

        try {
            $lock->block(5, function () use ($tvSeriesRepository): void {
                $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);
                if ($existing) {
                    $this->refreshExistingTvSeries($existing);

                    return;
                }

                [$tvSeries, $description, $localeValue, $contextTag] = $this->createTvSeriesRecord();

                $this->promoteDefaultIfEligible($tvSeries, $description);
                $this->invalidateTvSeriesCaches($tvSeries);
                $this->updateCache('DONE', $tvSeries->id, $tvSeries->slug, $description->id, $localeValue, $contextTag);
            });
        } catch (LockTimeoutException $exception) {
            /** @var TvSeriesRepository $tvSeriesRepository */
            $tvSeriesRepository = app(TvSeriesRepository::class);
            $existing = $tvSeriesRepository->findBySlugForJob($this->slug, $this->existingTvSeriesId);
            if ($existing) {
                $this->refreshExistingTvSeries($existing);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: TvSeries, 1: TvSeriesDescription, 2: string, 3: string}
     */
    private function createTvSeriesRecord(): array
    {
        // Simulate long-running AI generation (mock)
        sleep(3);

        $parsed = TvSeries::parseSlug($this->slug);
        $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $firstAirYear = $parsed['year'] ?? 2000;

        // Generate unique slug from parsed data
        $generatedSlug = TvSeries::generateSlug((string) $title, $firstAirYear);

        /** @var TvSeriesRepository $tvSeriesRepository */
        $tvSeriesRepository = app(TvSeriesRepository::class);
        $existingByGeneratedSlug = $tvSeriesRepository->findBySlugForJob($generatedSlug);
        if ($existingByGeneratedSlug) {
            $tvSeries = $existingByGeneratedSlug;
        } else {
            // Check if TV series exists by title + year
            $existingByTitleYear = TvSeries::where('title', (string) $title)
                ->whereYear('first_air_date', $firstAirYear)
                ->first();

            if ($existingByTitleYear) {
                $tvSeries = $existingByTitleYear;
            } else {
                // Create new TV series
                $tvSeries = TvSeries::create([
                    'title' => (string) $title,
                    'slug' => $generatedSlug,
                    'first_air_date' => "{$firstAirYear}-01-01",
                    'number_of_seasons' => 1,
                    'number_of_episodes' => 10,
                    'genres' => ['Drama', 'Comedy'],
                ]);
            }
        }

        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($tvSeries, $locale);

        $description = $this->persistDescription($tvSeries, $locale, $contextTag, [
            'text' => sprintf(
                'Generated description for %s (%s locale). This text was produced by MockGenerateTvSeriesJob (AI_SERVICE=mock).',
                $title,
                $locale->value
            ),
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-ai-1',
        ]);

        return [$tvSeries->fresh(['descriptions']), $description, $locale->value, $contextTag];
    }

    private function refreshExistingTvSeries(TvSeries $tvSeries): void
    {
        $tvSeries->loadMissing('descriptions');
        $locale = $this->resolveLocale();
        $description = $this->shouldUpdateBaseline($tvSeries, $locale)
            ? $this->updateBaselineDescription($tvSeries, $locale, [
                'text' => sprintf(
                    'Regenerated description for %s on %s (MockGenerateTvSeriesJob).',
                    $tvSeries->title,
                    now()->toIso8601String()
                ),
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-ai-1',
            ])
            : $this->persistDescription(
                $tvSeries,
                $locale,
                $this->determineContextTag($tvSeries, $locale),
                [
                    'text' => sprintf(
                        'Regenerated description for %s on %s (MockGenerateTvSeriesJob).',
                        $tvSeries->title,
                        now()->toIso8601String()
                    ),
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => 'mock-ai-1',
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

        $suffix = 2;
        do {
            $candidate = ContextTag::DEFAULT->value.'_'.$suffix;
            $suffix++;
        } while (in_array($candidate, $existingTags, true));

        return $candidate;
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('MockGenerateTvSeriesJob permanently failed', [
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
            Log::warning('MockGenerateTvSeriesJob default promotion lock timeout', [
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
}
