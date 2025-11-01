<?php

namespace App\Http\Controllers\Api;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRequest;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class GenerateController extends Controller
{
    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        // Support both 'slug' (new) and 'entity_id' (deprecated, backward compatibility)
        $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
        $jobId = (string) Str::uuid();

        return match ($entityType) {
            'MOVIE' => $this->handleMovieGeneration($slug, $jobId),
            'PERSON', 'ACTOR' => $this->handlePersonGeneration($slug, $jobId),
            default => response()->json(['error' => 'Invalid entity type'], 400),
        };
    }

    private function handleMovieGeneration(string $slug, string $jobId): JsonResponse
    {
        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Validate slug format
        $validation = SlugValidator::validateMovieSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        // Early return if already exists
        $existing = Movie::where('slug', $slug)->first();
        if ($existing) {
            return response()->json([
                'job_id' => $jobId,
                'status' => 'DONE',
                'message' => 'Movie already exists',
                'slug' => $slug,
                'id' => $existing->id,
                'confidence' => 1.0, // Existing data is always 100% confident
            ], 200);
        }

        // Set initial cache status with confidence
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => $slug,
            'confidence' => $validation['confidence'],
        ], now()->addMinutes(15));

        // Emit event - Listener will queue the Job
        event(new MovieGenerationRequested($slug, $jobId));

        return $this->queuedResponse($jobId, $slug, 'movie', $validation['confidence']);
    }

    private function handlePersonGeneration(string $slug, string $jobId): JsonResponse
    {
        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Validate slug format
        $validation = SlugValidator::validatePersonSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        // Early return if already exists
        $existing = Person::where('slug', $slug)->first();
        if ($existing) {
            return response()->json([
                'job_id' => $jobId,
                'status' => 'DONE',
                'message' => 'Person already exists',
                'slug' => $slug,
                'id' => $existing->id,
                'confidence' => 1.0, // Existing data is always 100% confident
            ], 200);
        }

        // Set initial cache status with confidence
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'PERSON',
            'slug' => $slug,
            'confidence' => $validation['confidence'],
        ], now()->addMinutes(15));

        // Emit event - Listener will queue the Job
        event(new PersonGenerationRequested($slug, $jobId));

        return $this->queuedResponse($jobId, $slug, 'person', $validation['confidence']);
    }

    private function queuedResponse(string $jobId, string $slug, string $entityName, float $confidence = 1.0): JsonResponse
    {
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => "Generation queued for {$entityName} by slug",
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->getConfidenceLevel($confidence),
        ], 202);
    }

    /**
     * Get human-readable confidence level.
     */
    private function getConfidenceLevel(float $confidence): string
    {
        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }
}
