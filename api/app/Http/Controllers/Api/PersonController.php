<?php

namespace App\Http\Controllers\Api;

use App\Events\PersonGenerationRequested;
use App\Http\Controllers\Controller;
use App\Repositories\PersonRepository;
use App\Services\HateoasService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class PersonController extends Controller
{
    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas
    ) {}

    public function show(string $slug)
    {
        $person = $this->personRepository->findBySlugWithRelations($slug);
        if ($person) {
            $payload = $person->toArray();
            $payload['_links'] = $this->hateoas->personLinks($person);

            return response()->json($payload);
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $jobId = (string) Str::uuid();

        // Set initial cache status
        Cache::put("ai_job:{$jobId}", [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'entity' => 'PERSON',
            'slug' => $slug,
        ], now()->addMinutes(15));

        // Emit event - Listener will queue the Job
        event(new PersonGenerationRequested($slug, $jobId));

        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for person by slug',
            'slug' => $slug,
        ], 202);
    }
}
