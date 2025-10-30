<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class PersonController extends Controller
{
    public function show(string $slug)
    {
        $person = Person::with(['bios', 'defaultBio', 'movies'])->where('slug', $slug)->first();
        if ($person) {
            $payload = $person->toArray();
            $payload['_links'] = [
                'self' => url("/api/v1/people/{$person->slug}"),
                'movies' => url('/api/v1/movies'),
            ];
            return response()->json($payload);
        }

        if (! Feature::active('ai_bio_generation')) {
            return response()->json(['error' => 'Person not found'], 404);
        }

        $jobId = (string) Str::uuid();
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for person by slug',
            'slug' => $slug,
        ], 202);
    }
}



