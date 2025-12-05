<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueuePersonGenerationAction;
use App\Helpers\SlugValidator;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\HateoasService;
use App\Services\PersonDisambiguationService;
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
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction,
        private readonly PersonDisambiguationService $disambiguationService,
        private readonly EntityVerificationServiceInterface $tmdbVerificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $people = $this->personRepository->searchPeople($q, 50);
        $data = $people->map(fn ($person) => $this->transformPerson($person));

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $bioId = $this->normalizeBioId($request->query('bio_id'));
        if ($bioId === false) {
            return response()->json([
                'error' => 'Invalid bio_id parameter',
            ], 422);
        }

        $cacheKey = $this->cacheKey($slug, $bioId);

        if ($cached = Cache::get($cacheKey)) {
            return response()->json($cached);
        }

        $person = $this->personRepository->findBySlugWithRelations($slug);
        if ($person) {
            $selectedBio = null;
            if ($bioId !== null) {
                $candidate = $person->bios->firstWhere('id', $bioId);
                if ($candidate instanceof PersonBio) {
                    $selectedBio = $candidate;
                }

                if ($selectedBio === null) {
                    return response()->json(['error' => 'Bio not found for person'], 404);
                }
            }

            $resource = PersonResource::make($person)
                ->additional(['_links' => $this->hateoas->personLinks($person)]);

            $payload = $resource->resolve($request);

            if ($selectedBio) {
                $payload['selected_bio'] = $selectedBio->toArray();
            }

            // Add disambiguation metadata if ambiguous slug
            $meta = $this->disambiguationService->determineMeta($person, $slug);
            if ($meta !== null) {
                $payload['_meta'] = $meta;
            }

            Cache::put($cacheKey, $payload, now()->addSeconds(self::CACHE_TTL_SECONDS));

            return response()->json($payload);
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        // Validate slug format and check for prompt injection
        $validation = SlugValidator::validatePersonSlug($slug);
        if (! $validation['valid']) {
            return response()->json([
                'error' => 'Invalid slug format',
                'message' => $validation['reason'],
                'confidence' => $validation['confidence'],
                'slug' => $slug,
            ], 400);
        }

        // Verify person exists in TMDb before queueing job
        $tmdbData = $this->tmdbVerificationService->verifyPerson($slug);
        if (! $tmdbData) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $result = $this->queuePersonGenerationAction->handle(
            $slug,
            tmdbData: $tmdbData
        );

        return response()->json($result, 202);
    }

    private function transformPerson(Person $person): array
    {
        $resource = PersonResource::make($person)->additional([
            '_links' => $this->hateoas->personLinks($person),
        ]);

        return $resource->resolve();
    }

    private function cacheKey(string $slug, ?int $bioId = null): string
    {
        $suffix = $bioId !== null ? 'bio:'.$bioId : 'bio:default';

        return 'person:'.$slug.':'.$suffix;
    }

    private function normalizeBioId(mixed $bioId): null|int|false
    {
        if ($bioId === null || $bioId === '') {
            return null;
        }

        if (filter_var($bioId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return false;
        }

        return (int) $bioId;
    }
}
