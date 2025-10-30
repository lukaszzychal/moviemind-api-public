<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Repositories\MovieRepository;
use App\Services\HateoasService;
use Laravel\Pennant\Feature;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly HateoasService $hateoas
    ) {}

    public function index(Request $request)
    {
        $q = $request->query('q');
        $movies = $this->movieRepository->searchMovies($q, 50);

        $data = $movies->map(function (Movie $m) {
            $arr = $m->toArray();
            $arr['_links'] = $this->hateoas->movieLinks($m);
            return $arr;
        });

        return response()->json(['data' => $data]);
    }

    public function show(string $slug)
    {
        $movie = $this->movieRepository->findBySlugWithRelations($slug);
        if ($movie) {
            $payload = $movie->toArray();
            $payload['_links'] = $this->hateoas->movieLinks($movie);
            return response()->json($payload);
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


