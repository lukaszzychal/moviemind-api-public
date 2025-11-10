<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
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
 * Mock Generate Movie Job - simulates AI generation for development/testing.
 * Used when AI_SERVICE=mock.
 */
class MockGenerateMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?int $existingMovieId = null,
        public ?int $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null
    ) {}

    public function handle(): void
    {
        try {
            $existing = $this->findExistingMovie();

            if ($existing) {
                $this->refreshExistingMovie($existing);

                return;
            }

            $this->createMovieWithLock();
        } catch (\Throwable $e) {
            Log::error('MockGenerateMovieJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateCache('FAILED');

            throw $e;
        }
    }

    private function findExistingMovie(): ?Movie
    {
        if ($this->existingMovieId !== null) {
            $movie = Movie::with('descriptions')->find($this->existingMovieId);
            if ($movie) {
                return $movie;
            }
        }

        return Movie::with('descriptions')->where('slug', $this->slug)->first();
    }

    private function createMovieWithLock(): void
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        try {
            $lock->block(5, function (): void {
                $existing = $this->findExistingMovie();
                if ($existing) {
                    $this->refreshExistingMovie($existing);

                    return;
                }

                [$movie, $description, $localeValue, $contextTag] = $this->createMovieRecord();

                $this->promoteDefaultIfEligible($movie, $description);
                $this->invalidateMovieCaches($movie);
                $this->updateCache('DONE', $movie->id, $movie->slug, $description->id, $localeValue, $contextTag);
            });
        } catch (LockTimeoutException $exception) {
            $existing = $this->findExistingMovie();
            if ($existing) {
                $this->refreshExistingMovie($existing);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: Movie, 1: MovieDescription, 2: string, 3: string}
     */
    private function createMovieRecord(): array
    {
        // Simulate long-running AI generation (mock)
        sleep(3);

        $parsed = Movie::parseSlug($this->slug);
        $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $releaseYear = $parsed['year'] ?? 1999;
        $director = $parsed['director'] ?? 'Mock AI Director';

        $movie = Movie::create([
            'title' => (string) $title,
            'slug' => $this->slug,
            'release_year' => $releaseYear,
            'director' => $director,
            'genres' => ['Sci-Fi', 'Action'],
        ]);

        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($movie, $locale);

        $description = $this->persistDescription($movie, $locale, $contextTag, [
            'text' => sprintf(
                'Generated description for %s (%s locale). This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).',
                $title,
                $locale->value
            ),
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-ai-1',
        ]);

        return [$movie->fresh(['descriptions']), $description, $locale->value, $contextTag];
    }

    private function refreshExistingMovie(Movie $movie): void
    {
        $movie->loadMissing('descriptions');
        $locale = $this->resolveLocale();
        $contextTag = $this->determineContextTag($movie, $locale);

        $description = $this->persistDescription($movie, $locale, $contextTag, [
            'text' => sprintf(
                'Regenerated description for %s on %s (MockGenerateMovieJob).',
                $movie->title,
                now()->toIso8601String()
            ),
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-ai-1',
        ]);

        $this->promoteDefaultIfEligible($movie, $description);
        $this->invalidateMovieCaches($movie);
        $this->updateCache('DONE', $movie->id, $movie->slug, $description->id, $locale->value, $contextTag);
    }

    private function updateCache(
        string $status,
        ?int $id = null,
        ?string $slug = null,
        ?int $descriptionId = null,
        ?string $locale = null,
        ?string $contextTag = null
    ): void {
        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'MOVIE',
            'slug' => $slug ?? $this->slug,
            'requested_slug' => $this->slug,
            'id' => $id,
            'description_id' => $descriptionId,
            'locale' => $locale ?? $this->locale,
            'context_tag' => $contextTag ?? $this->contextTag,
        ], now()->addMinutes(15));
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

    private function determineContextTag(Movie $movie, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($movie);
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

    private function persistDescription(Movie $movie, Locale $locale, string $contextTag, array $attributes): MovieDescription
    {
        $existing = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return MovieDescription::create(array_merge([
            'movie_id' => $movie->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function nextContextTag(Movie $movie): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $movie->descriptions()->pluck('context_tag')->all()
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
        Log::error('MockGenerateMovieJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => 'FAILED',
            'entity' => 'MOVIE',
            'slug' => $this->slug,
            'requested_slug' => $this->slug,
            'error' => $exception->getMessage(),
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
        ], now()->addMinutes(15));
    }

    private function promoteDefaultIfEligible(Movie $movie, MovieDescription $description): void
    {
        $lock = Cache::lock($this->defaultLockKey($movie), 10);

        try {
            $lock->block(5, function () use ($movie, $description): void {
                $movie->refresh();
                $currentDefault = $movie->default_description_id;

                if ($this->baselineDescriptionId !== null) {
                    if ((int) $currentDefault !== $this->baselineDescriptionId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $movie->default_description_id = $description->id;
                $movie->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('MockGenerateMovieJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'movie_id' => $movie->id,
            ]);
        }
    }

    private function creationLockKey(): string
    {
        return 'lock:movie:create:'.$this->slug;
    }

    private function defaultLockKey(Movie $movie): string
    {
        return 'lock:movie:default:'.$movie->id;
    }

    private function invalidateMovieCaches(Movie $movie): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $movie->slug,
        ]));

        $descriptionIds = $movie->descriptions()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('movie:'.$slug.':desc:default');

            foreach ($descriptionIds as $descriptionId) {
                Cache::forget('movie:'.$slug.':desc:'.$descriptionId);
            }
        }
    }
}
