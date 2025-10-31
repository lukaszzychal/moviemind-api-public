<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Real Generate Movie Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGenerateMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId
    ) {}

    // Note: OpenAiClientInterface is resolved via service container in handle()
    // We can't inject it in constructor because Job is serialized to queue

    public function handle(): void
    {
        try {
            // Check if movie already exists
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Double-check (race condition protection)
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Call real AI API using OpenAiClient
            $openAiClient = app(OpenAiClientInterface::class);
            $aiResponse = $openAiClient->generateMovie($this->slug);

            if (! $aiResponse || ! isset($aiResponse['success']) || ! $aiResponse['success']) {
                throw new \RuntimeException('AI API returned error: '.($aiResponse['error'] ?? 'Unknown error'));
            }

            $title = $aiResponse['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
            $releaseYear = $aiResponse['release_year'] ?? 1999;
            $director = $aiResponse['director'] ?? 'Unknown Director';
            $description = $aiResponse['description'] ?? "Generated description for {$title}.";
            $genres = $aiResponse['genres'] ?? ['Action', 'Drama'];

            // Generate unique slug
            $uniqueSlug = Movie::generateSlug($title, $releaseYear, $director);

            $movie = Movie::create([
                'title' => (string) $title,
                'slug' => $uniqueSlug,
                'release_year' => $releaseYear,
                'director' => $director,
                'genres' => $genres,
            ]);

            $desc = MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => Locale::EN_US,
                'text' => (string) $description,
                'context_tag' => ContextTag::DEFAULT,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ]);

            $movie->default_description_id = $desc->id;
            $movie->save();

            $this->updateCache('DONE', $movie->id, $uniqueSlug);
        } catch (\Throwable $e) {
            Log::error('RealGenerateMovieJob failed', [
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
        Log::error('RealGenerateMovieJob permanently failed', [
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
