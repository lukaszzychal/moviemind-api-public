<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Laravel\Pennant\Feature;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $movies = Movie::query()
            ->when($q, function ($query) use ($q) {
                $query->where('title', 'ILIKE', "%$q%")
                      ->orWhere('director', 'ILIKE', "%$q%")
                      ->orWhereHas('genres', function ($qg) use ($q) {
                          $qg->where('name', 'ILIKE', "%$q%");
                      });
            })
            ->with(['defaultDescription', 'genres', 'people'])
            ->limit(50)
            ->get();

        return response()->json(['data' => $movies]);
    }

    public function show(string $slug)
    {
        $movie = Movie::with(['descriptions', 'defaultDescription'])->where('slug', $slug)->first();
        if ($movie) {
            return response()->json($movie);
        }

        if (! Feature::active('ai_description_generation')) {
            return response()->json(['error' => 'Movie not found'], 404);
        }

        $jobId = (string) Str::uuid();
        return response()->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
        ], 202);
    }
}


