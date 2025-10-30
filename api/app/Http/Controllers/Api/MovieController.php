<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
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

    public function show(int $id)
    {
        $movie = Movie::with(['descriptions', 'defaultDescription'])->findOrFail($id);
        return response()->json($movie);
    }
}


