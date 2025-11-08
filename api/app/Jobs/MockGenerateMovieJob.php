<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Bus\Queueable;
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
        public string $jobId
    ) {}

    public function handle(): void
    {
        try {
            // Check if movie already exists
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->invalidateCache($this->slug);
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Simulate long-running AI generation (mock)
            sleep(3);

            // Double-check (race condition protection)
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->invalidateCache($this->slug);
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Parse slug to extract title and year if available
            $parsed = Movie::parseSlug($this->slug);
            $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
            $releaseYear = $parsed['year'] ?? 1999;
            $director = $parsed['director'] ?? 'Mock AI Director';

            // Generate unique slug using the new method (handles duplicates)
            $uniqueSlug = Movie::generateSlug($title, $releaseYear, $director);

            $movie = Movie::create([
                'title' => (string) $title,
                'slug' => $uniqueSlug,
                'release_year' => $releaseYear,
                'director' => $director,
                'genres' => ['Sci-Fi', 'Action'],
            ]);

            $desc = MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => Locale::EN_US,
                'text' => "Generated description for {$title}. This text was produced by MockGenerateMovieJob (AI_SERVICE=mock).",
                'context_tag' => ContextTag::DEFAULT,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-ai-1',
            ]);

            $movie->default_description_id = $desc->id;
            $movie->save();

            $this->invalidateCache($this->slug, $uniqueSlug);
            $this->updateCache('DONE', $movie->id, $uniqueSlug);
        } catch (\Throwable $e) {
            Log::error('MockGenerateMovieJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateCache('FAILED');

            throw $e; // Re-throw for retry mechanism
        }
    }

    private function updateCache(string $status, ?int $id = null, ?string $slug = null): void
    {
        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'MOVIE',
            'slug' => $slug ?? $this->slug,
            'id' => $id,
        ], now()->addMinutes(15));
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    private function invalidateCache(string ...$slugs): void
    {
        foreach (array_filter($slugs) as $slug) {
            Cache::forget('movie:'.$slug);
        }
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
}
