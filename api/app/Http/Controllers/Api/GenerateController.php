<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRequest;
use App\Services\AiServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class GenerateController extends Controller
{
    public function __construct(private readonly AiServiceInterface $ai) {}

    public function generate(GenerateRequest $request)
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        $slug = (string) $validated['entity_id'];

        return match ($entityType) {
            'MOVIE' => $this->handleMovieGeneration($slug),
            'PERSON' => $this->handlePersonGeneration($slug),
            default => response()->json([
                'error' => 'Invalid entity type',
            ], 400),
        };
    }

    private function handleMovieGeneration(string $slug): JsonResponse
    {
        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $jobId = (string) Str::uuid();
        $this->ai->queueMovieGeneration($slug, $jobId);

        return $this->queuedResponse($jobId, $slug, 'movie');
    }

    private function handlePersonGeneration(string $slug): JsonResponse
    {
        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $jobId = (string) Str::uuid();
        $this->ai->queuePersonGeneration($slug, $jobId);

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
