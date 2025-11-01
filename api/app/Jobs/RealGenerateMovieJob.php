<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Enums\RoleType;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\Person;
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

    /**
     * Execute the job.
     * Note: OpenAiClientInterface is injected via method injection.
     * Constructor injection is not possible because Jobs are serialized to queue.
     */
    public function handle(OpenAiClientInterface $openAiClient): void
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

            // Call real AI API using OpenAiClient (injected via method injection)
            $aiResponse = $openAiClient->generateMovie($this->slug);

            // Check if AI response is successful
            // PHPStan: 'success' key always exists in array return type
            if (! $aiResponse['success']) {
                $error = $aiResponse['error'] ?? 'Unknown error';
                throw new \RuntimeException('AI API returned error: '.$error);
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
                'director' => $director, // Keep for backward compatibility, but also create Person relation
                'genres' => $genres,
            ]);

            // Create or find director as Person and link via movie_person
            if ($director && $director !== 'Unknown Director') {
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
