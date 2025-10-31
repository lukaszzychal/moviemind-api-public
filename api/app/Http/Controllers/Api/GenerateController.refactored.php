<?php

namespace App\Http\Controllers\Api;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRequest;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Refactored version using Events + Jobs instead of Service.
 * 
 * To use this:
 * 1. Rename to GenerateController.php (backup old one)
 * 2. Remove AiServiceInterface dependency
 * 3. Test thoroughly
 */
class GenerateControllerRefactored extends Controller
{
    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        $slug = (string) $validated['entity_id'];
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

        // Early return if already exists
        $existing = Movie::where('slug', $slug)->first();
        if ($existing) {
            return response()->json([
                'job_id' => $jobId,
                'status' => 'DONE',
                'message' => 'Movie already exists',
                'slug' => $slug,
                'id' => $existing->id,
            ], 200);
        }

        // Set initial cache status
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'MOVIE',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Emit event instead of calling service
        event(new MovieGenerationRequested($slug, $jobId));

        return $this->queuedResponse($jobId, $slug, 'movie');
    }

    private function handlePersonGeneration(string $slug, string $jobId): JsonResponse
    {
        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
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
            ], 200);
        }

        // Set initial cache status
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'PERSON',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Emit event instead of calling service
        event(new PersonGenerationRequested($slug, $jobId));

        return $this->queuedResponse($jobId, $slug, 'person');
    }

    private function queuedResponse(string $jobId, string $slug, string $entityName): JsonResponse
    {
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => "Generation queued for {$entityName} by slug",
            'slug' => $slug,
        ], 202);
    }
}

