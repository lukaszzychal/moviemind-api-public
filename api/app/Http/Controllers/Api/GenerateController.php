<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Actions\QueuePersonGenerationAction;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRequest;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Laravel\Pennant\Feature;

class GenerateController extends Controller
{
    public function __construct(
        private readonly QueueMovieGenerationAction $queueMovieGenerationAction,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction
    ) {}

    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');

        return match ($entityType) {
            'MOVIE' => $this->handleMovieGeneration($slug),
            'PERSON', 'ACTOR' => $this->handlePersonGeneration($slug),
            default => response()->json(['error' => 'Invalid entity type'], 400),
        };
    }

    private function handleMovieGeneration(string $slug): JsonResponse
    {
        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $validation = SlugValidator::validateMovieSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        $existing = Movie::where('slug', $slug)->first();

        $result = $this->queueMovieGenerationAction->handle($slug, $validation['confidence']);

        if ($existing) {
            $result['message'] = 'Generation queued for existing movie slug';
            $result['existing_id'] = $existing->id;
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        }

        return response()->json($result, 202);
    }

    private function handlePersonGeneration(string $slug): JsonResponse
    {
        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $validation = SlugValidator::validatePersonSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        $existing = Person::where('slug', $slug)->first();

        $result = $this->queuePersonGenerationAction->handle($slug, $validation['confidence']);

        if ($existing) {
            $result['message'] = 'Generation queued for existing person slug';
            $result['existing_id'] = $existing->id;
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        }

        return response()->json($result, 202);
    }

    private function confidenceLevel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }
}
