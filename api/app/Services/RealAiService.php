<?php

namespace App\Services;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Real AI Service - dispatches Events instead of Jobs directly.
 * 
 * This service uses the Events + Jobs architecture.
 * The actual AI generation is handled by GenerateMovieJob/GeneratePersonJob
 * which can be configured to call real AI APIs.
 */
class RealAiService implements AiServiceInterface
{
    public function queueMovieGeneration(string $slug, string $jobId): void
    {
        // Check if movie already exists
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

        // Set initial cache status
        Cache::put($this->cacheKey($jobId), [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Dispatch Event - Listener will queue the Job
        event(new MovieGenerationRequested($slug, $jobId));
    }

    public function queuePersonGeneration(string $slug, string $jobId): void
    {
        // Check if person already exists
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

        // Set initial cache status
        Cache::put($this->cacheKey($jobId), [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'PERSON',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Dispatch Event - Listener will queue the Job
        event(new PersonGenerationRequested($slug, $jobId));
    }

    private function cacheKey(string $jobId): string
    {
        return 'ai_job:'.$jobId;
    }
}

