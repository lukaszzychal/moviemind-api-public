<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Enums\RoleType;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\Person;
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
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Simulate long-running AI generation (mock)
            sleep(3);

            // Double-check (race condition protection)
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
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
                'director' => $director, // Keep for backward compatibility, but also create Person relation
                'genres' => ['Sci-Fi', 'Action'],
            ]);

            // Create or find director as Person and link via movie_person
            if ($director && $director !== 'Mock AI Director') {
                $directorSlug = Str::slug($director);
                $directorPerson = Person::firstOrCreate(
                    ['slug' => $directorSlug],
                    ['name' => $director]
                );

                // Attach director relationship
                $movie->people()->attach($directorPerson->id, [
                    'role' => RoleType::DIRECTOR->value,
                    'billing_order' => null, // Director doesn't have billing order
                ]);
            }

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
