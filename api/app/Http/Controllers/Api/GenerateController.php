<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class GenerateController extends Controller
{
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:MOVIE,ACTOR,PERSON',
            'entity_id' => 'required|integer',
            'locale' => 'nullable|string|max:10',
            'context_tag' => 'nullable|string|max:64',
        ]);

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        $jobId = (string) Str::uuid();
        // Mock response; in real flow dispatch job to queue
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
        ]);
    }
}


