<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueMovieGenerationAction;
use App\Actions\QueuePersonGenerationAction;
use App\Actions\QueueTvSeriesGenerationAction;
use App\Actions\QueueTvShowGenerationAction;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRequest;
use App\Models\Movie;
use App\Models\Person;
use App\Models\TvSeries;
use App\Models\TvShow;
use Illuminate\Http\JsonResponse;
use Laravel\Pennant\Feature;

class GenerateController extends Controller
{
    public function __construct(
        private readonly QueueMovieGenerationAction $queueMovieGenerationAction,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction,
        private readonly QueueTvSeriesGenerationAction $queueTvSeriesGenerationAction,
        private readonly QueueTvShowGenerationAction $queueTvShowGenerationAction
    ) {}

    public function generate(GenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $entityType = $validated['entity_type'];
        $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
        $contextTags = $validated['context_tag'] ?? null; // Can be array or null

        return match ($entityType) {
            'MOVIE' => $this->handleMovieGeneration($slug, $validated['locale'] ?? null, $contextTags),
            'PERSON', 'ACTOR' => $this->handlePersonGeneration($slug, $validated['locale'] ?? null, $contextTags),
            'TV_SERIES' => $this->handleTvSeriesGeneration($slug, $validated['locale'] ?? null, $contextTags),
            'TV_SHOW' => $this->handleTvShowGeneration($slug, $validated['locale'] ?? null, $contextTags),
            default => response()->json(['error' => 'Invalid entity type'], 400),
        };
    }

    /**
     * Handle movie generation with support for multiple context tags.
     *
     * @param  array<string>|null  $contextTags  Array of context tags or null
     */
    private function handleMovieGeneration(string $slug, ?string $locale = null, ?array $contextTags = null): JsonResponse
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

        // Handle multiple context tags: queue a job for each context tag
        if (is_array($contextTags) && count($contextTags) > 1) {
            $results = [];
            foreach ($contextTags as $contextTag) {
                $result = $this->queueMovieGenerationAction->handle(
                    $slug,
                    $validation['confidence'],
                    $existing,
                    $locale,
                    $contextTag
                );
                $results[] = $result;
            }

            // Return first job_id and list of all queued jobs
            return response()->json([
                'job_ids' => array_column($results, 'job_id'),
                'status' => 'PENDING',
                'message' => 'Generation queued for multiple context tags',
                'slug' => $slug,
                'context_tags' => $contextTags,
                'locale' => $locale ?? 'en-US',
                'jobs' => $results,
            ], 202);
        }

        // Single context tag (backward compatibility) or null
        // If array with 1 element, extract it; if empty array, treat as null; otherwise use as-is
        $contextTag = null;
        if (is_array($contextTags)) {
            if (count($contextTags) === 1) {
                $contextTag = $contextTags[0];
            } elseif (count($contextTags) === 0) {
                $contextTag = null;
            }
        } else {
            $contextTag = $contextTags;
        }
        $result = $this->queueMovieGenerationAction->handle(
            $slug,
            $validation['confidence'],
            $existing,
            $locale,
            $contextTag
        );

        if ($existing) {
            if (($result['message'] ?? '') !== 'Generation already queued for movie slug') {
                $result['message'] = 'Generation queued for existing movie slug';
            }
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        } else {
            unset($result['existing_id'], $result['description_id']);
        }

        return response()->json($result, 202);
    }

    /**
     * Handle person generation with support for multiple context tags.
     *
     * @param  array<string>|null  $contextTags  Array of context tags or null
     */
    private function handlePersonGeneration(string $slug, ?string $locale = null, ?array $contextTags = null): JsonResponse
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

        // Handle multiple context tags: queue a job for each context tag
        if (is_array($contextTags) && count($contextTags) > 1) {
            $results = [];
            foreach ($contextTags as $contextTag) {
                $result = $this->queuePersonGenerationAction->handle(
                    $slug,
                    $validation['confidence'],
                    $existing,
                    $locale,
                    $contextTag
                );
                $results[] = $result;
            }

            // Return first job_id and list of all queued jobs
            return response()->json([
                'job_ids' => array_column($results, 'job_id'),
                'status' => 'PENDING',
                'message' => 'Generation queued for multiple context tags',
                'slug' => $slug,
                'context_tags' => $contextTags,
                'locale' => $locale ?? 'en-US',
                'jobs' => $results,
            ], 202);
        }

        // Single context tag (backward compatibility) or null
        // If array with 1 element, extract it; if empty array, treat as null; otherwise use as-is
        $contextTag = null;
        if (is_array($contextTags)) {
            if (count($contextTags) === 1) {
                $contextTag = $contextTags[0];
            } elseif (count($contextTags) === 0) {
                $contextTag = null;
            }
        } else {
            $contextTag = $contextTags;
        }
        $result = $this->queuePersonGenerationAction->handle(
            $slug,
            $validation['confidence'],
            $existing,
            $locale,
            $contextTag
        );

        if ($existing) {
            if (($result['message'] ?? '') !== 'Generation already queued for person slug') {
                $result['message'] = 'Generation queued for existing person slug';
            }
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        } else {
            unset($result['existing_id'], $result['bio_id']);
        }

        return response()->json($result, 202);
    }

    /**
     * Handle TV series generation with support for multiple context tags.
     *
     * @param  array<string>|null  $contextTags  Array of context tags or null
     */
    private function handleTvSeriesGeneration(string $slug, ?string $locale = null, ?array $contextTags = null): JsonResponse
    {
        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $validation = SlugValidator::validateTvSeriesSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        $existing = TvSeries::where('slug', $slug)->first();

        // Handle multiple context tags: queue a job for each context tag
        if (is_array($contextTags) && count($contextTags) > 1) {
            $results = [];
            foreach ($contextTags as $contextTag) {
                $result = $this->queueTvSeriesGenerationAction->handle(
                    $slug,
                    $validation['confidence'],
                    $existing,
                    $locale,
                    $contextTag
                );
                $results[] = $result;
            }

            return response()->json([
                'job_ids' => array_column($results, 'job_id'),
                'status' => 'PENDING',
                'message' => 'Generation queued for multiple context tags',
                'slug' => $slug,
                'context_tags' => $contextTags,
                'locale' => $locale ?? 'en-US',
                'jobs' => $results,
            ], 202);
        }

        // Single context tag (backward compatibility) or null
        $contextTag = null;
        if (is_array($contextTags)) {
            if (count($contextTags) === 1) {
                $contextTag = $contextTags[0];
            } elseif (count($contextTags) === 0) {
                $contextTag = null;
            }
        } else {
            $contextTag = $contextTags;
        }
        $result = $this->queueTvSeriesGenerationAction->handle(
            $slug,
            $validation['confidence'],
            $existing,
            $locale,
            $contextTag
        );

        if ($existing) {
            if (($result['message'] ?? '') !== 'Generation already queued for TV series slug') {
                $result['message'] = 'Generation queued for existing TV series slug';
            }
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        } else {
            unset($result['existing_id'], $result['description_id']);
        }

        return response()->json($result, 202);
    }

    /**
     * Handle TV show generation with support for multiple context tags.
     *
     * @param  array<string>|null  $contextTags  Array of context tags or null
     */
    private function handleTvShowGeneration(string $slug, ?string $locale = null, ?array $contextTags = null): JsonResponse
    {
        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $validation = SlugValidator::validateTvShowSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        $existing = TvShow::where('slug', $slug)->first();

        // Handle multiple context tags: queue a job for each context tag
        if (is_array($contextTags) && count($contextTags) > 1) {
            $results = [];
            foreach ($contextTags as $contextTag) {
                $result = $this->queueTvShowGenerationAction->handle(
                    $slug,
                    $validation['confidence'],
                    $existing,
                    $locale,
                    $contextTag
                );
                $results[] = $result;
            }

            return response()->json([
                'job_ids' => array_column($results, 'job_id'),
                'status' => 'PENDING',
                'message' => 'Generation queued for multiple context tags',
                'slug' => $slug,
                'context_tags' => $contextTags,
                'locale' => $locale ?? 'en-US',
                'jobs' => $results,
            ], 202);
        }

        // Single context tag (backward compatibility) or null
        $contextTag = null;
        if (is_array($contextTags)) {
            if (count($contextTags) === 1) {
                $contextTag = $contextTags[0];
            } elseif (count($contextTags) === 0) {
                $contextTag = null;
            }
        } else {
            $contextTag = $contextTags;
        }
        $result = $this->queueTvShowGenerationAction->handle(
            $slug,
            $validation['confidence'],
            $existing,
            $locale,
            $contextTag
        );

        if ($existing) {
            if (($result['message'] ?? '') !== 'Generation already queued for TV show slug') {
                $result['message'] = 'Generation queued for existing TV show slug';
            }
            $result['confidence'] = $validation['confidence'];
            $result['confidence_level'] = $this->confidenceLevel($validation['confidence']);
        } else {
            unset($result['existing_id'], $result['description_id']);
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
