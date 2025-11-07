<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueuePersonGenerationAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PersonResource;
use App\Repositories\PersonRepository;
use App\Services\HateoasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class PersonController extends Controller
{
    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas,
        private readonly QueuePersonGenerationAction $queuePersonGenerationAction
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);
        if ($person) {
            return response()->json(
                PersonResource::make($person)
                    ->additional(['_links' => $this->hateoas->personLinks($person)])
                    ->resolve($request)
            );
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $result = $this->queuePersonGenerationAction->handle($slug);

        return response()->json($result, 202);
    }
}
