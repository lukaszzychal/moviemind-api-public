<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueuePersonGenerationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Repositories\PersonRepository;
use App\Services\HateoasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

class PersonController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        if ($cached = Cache::get($this->cacheKey($slug))) {
            return response()->json($cached);
        }

        $person = $this->personRepository->findBySlugWithRelations($slug);
        if ($person) {
            $payload = PersonResource::make($person)
                ->additional(['_links' => $this->hateoas->personLinks($person)])
                ->resolve($request);
            Cache::put($this->cacheKey($slug), $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return response()->json($payload);
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $result = $this->queuePersonGenerationAction->handle($slug);

        return response()->json($result, 202);
    }

    private function cacheKey(string $slug): string
    {
        return 'person:'.$slug;
    }
}
