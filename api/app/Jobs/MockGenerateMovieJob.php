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
        public ?int $baselineDescriptionId = null
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

                [$movie, $description] = $this->createMovieRecord();

                $this->promoteDefaultIfEligible($movie, $description);
                $this->invalidateMovieCaches($movie);
                $this->updateCache('DONE', $movie->id, $movie->slug, $description->id);
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
     * @return array{0: Movie, 1: MovieDescription}
     */
    private function createMovieRecord(): array
    {
        // Simulate long-running AI generation (mock)
        sleep(3);

        $parsed = Movie::parseSlug($this->slug);
        $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $releaseYear = $parsed['year'] ?? 1999;
        $director = $parsed['director'] ?? 'Mock AI Director';

        $uniqueSlug = Movie::generateSlug($title, $releaseYear, $director);

        $movie = Movie::create([
            'title' => (string) $title,
            'slug' => $uniqueSlug,
            'release_year' => $releaseYear,
            'director' => $director,
            'genres' => ['Sci-Fi', 'Action'],
        ]);

        $contextTag = $this->nextContextTag($movie);

        $description = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'text' => "Generated description for {$title}. This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).",
            'context_tag' => $contextTag,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-ai-1',
        ]);

        return [$movie->fresh(['descriptions']), $description];
    }

    private function refreshExistingMovie(Movie $movie): void
    {
        $movie->loadMissing('descriptions');
        $contextTag = $this->nextContextTag($movie);

        $description = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'text' => sprintf(
                'Regenerated description for %s on %s (MockGenerateMovieJob).',
                $movie->title,
                now()->toIso8601String()
            ),
            'context_tag' => $contextTag,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock-ai-1',
        ]);

        $this->promoteDefaultIfEligible($movie, $description);
        $this->invalidateMovieCaches($movie);
        $this->updateCache('DONE', $movie->id, $movie->slug, $description->id);
    }

    private function updateCache(string $status, ?int $id = null, ?string $slug = null, ?int $descriptionId = null): void
    {
        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'MOVIE',
            'slug' => $slug ?? $this->slug,
            'id' => $id,
            'description_id' => $descriptionId,
        ], now()->addMinutes(15));
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
            'error' => $exception->getMessage(),
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
