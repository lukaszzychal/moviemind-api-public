<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MockAiService implements AiServiceInterface
{
    public function queueMovieGeneration(string $slug, string $jobId): void
    {
        // If movie already exists, immediately mark job as DONE and skip dispatch
        $already = Movie::where('slug', $slug)->first();
        if ($already) {
            Cache::put($this->cacheKey($jobId), [
                'job_id' => $jobId,
                'status' => 'DONE',
                'entity' => 'MOVIE',
                'slug' => $slug,
                'id' => $already->id,
            ], now()->addMinutes(15));

            return;
        }

        Cache::put($this->cacheKey($jobId), [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => $slug,
        ], now()->addMinutes(15));

        Bus::dispatch(function () use ($slug, $jobId) {
            try {
                // Simulate long-running AI generation
                sleep(3);

                $existing = Movie::where('slug', $slug)->first();
                if ($existing) {
                    Cache::put($this->cacheKey($jobId), [
                        'job_id' => $jobId,
                        'status' => 'DONE',
                        'entity' => 'MOVIE',
                        'slug' => $slug,
                        'id' => $existing->id,
                    ], now()->addMinutes(15));

                    return;
                }

                // Parse slug to extract title and year if available
                $parsed = Movie::parseSlug($slug);
                $title = $parsed['title'] ?? Str::of($slug)->replace('-', ' ')->title();
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
                    'locale' => 'en_US',
                    'text' => "Generated description for {$title}. This text was produced by MockAiService.",
                    'context_tag' => 'DEFAULT',
                    'origin' => 'GENERATED',
                    'ai_model' => 'mock-ai-1',
                ]);

                $movie->default_description_id = $desc->id;
                $movie->save();

                Cache::put($this->cacheKey($jobId), [
                    'job_id' => $jobId,
                    'status' => 'DONE',
                    'entity' => 'MOVIE',
                    'slug' => $uniqueSlug, // Use the actual generated slug
                    'id' => $movie->id,
                ], now()->addMinutes(15));
            } catch (\Throwable $e) {
                Log::error('MockAiService movie generation failed', [
                    'slug' => $slug,
                    'job_id' => $jobId,
                    'error' => $e->getMessage(),
                ]);
                Cache::put($this->cacheKey($jobId), [
                    'job_id' => $jobId,
                    'status' => 'FAILED',
                    'entity' => 'MOVIE',
                    'slug' => $slug,
                ], now()->addMinutes(15));
            }
        });
    }

    public function queuePersonGeneration(string $slug, string $jobId): void
    {
        // If person already exists, immediately mark job as DONE and skip dispatch
        $already = Person::where('slug', $slug)->first();
        if ($already) {
            Cache::put($this->cacheKey($jobId), [
                'job_id' => $jobId,
                'status' => 'DONE',
                'entity' => 'PERSON',
                'slug' => $slug,
                'id' => $already->id,
            ], now()->addMinutes(15));

            return;
        }

        Cache::put($this->cacheKey($jobId), [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'PERSON',
            'slug' => $slug,
        ], now()->addMinutes(15));

        Bus::dispatch(function () use ($slug, $jobId) {
            try {
                // Simulate long-running AI generation
                sleep(3);

                $existing = Person::where('slug', $slug)->first();
                if ($existing) {
                    Cache::put($this->cacheKey($jobId), [
                        'job_id' => $jobId,
                        'status' => 'DONE',
                        'entity' => 'PERSON',
                        'slug' => $slug,
                        'id' => $existing->id,
                    ], now()->addMinutes(15));

                    return;
                }

                $name = Str::of($slug)->replace('-', ' ')->title();
                $person = Person::create([
                    'name' => (string) $name,
                    'slug' => $slug,
                    'birth_date' => '1970-01-01',
                    'birthplace' => 'Mock City',
                ]);

                $bio = PersonBio::create([
                    'person_id' => $person->id,
                    'locale' => 'en_US',
                    'text' => "Generated biography for {$name}. This text was produced by MockAiService.",
                    'context_tag' => 'DEFAULT',
                    'origin' => 'GENERATED',
                    'ai_model' => 'mock-ai-1',
                ]);

                $person->default_bio_id = $bio->id;
                $person->save();

                Cache::put($this->cacheKey($jobId), [
                    'job_id' => $jobId,
                    'status' => 'DONE',
                    'entity' => 'PERSON',
                    'slug' => $slug,
                    'id' => $person->id,
                ], now()->addMinutes(15));
            } catch (\Throwable $e) {
                Log::error('MockAiService person generation failed', [
                    'slug' => $slug,
                    'job_id' => $jobId,
                    'error' => $e->getMessage(),
                ]);
                Cache::put($this->cacheKey($jobId), [
                    'job_id' => $jobId,
                    'status' => 'FAILED',
                    'entity' => 'PERSON',
                    'slug' => $slug,
                ], now()->addMinutes(15));
            }
        });
    }

    private function cacheKey(string $jobId): string
    {
        return 'ai_job:'.$jobId;
    }
}
