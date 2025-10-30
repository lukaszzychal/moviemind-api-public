<?php

namespace App\Repositories;

use App\Models\Movie;
use Illuminate\Support\Collection;

class MovieRepository
{
    public function searchMovies(?string $query, int $limit = 50): Collection
    {
        return Movie::query()
            ->when($query, function ($builder) use ($query) {
                $builder->where('title', 'ILIKE', "%$query%")
                    ->orWhere('director', 'ILIKE', "%$query%")
                    ->orWhereHas('genres', function ($qg) use ($query) {
                        $qg->where('name', 'ILIKE', "%$query%");
                    });
            })
            ->with(['defaultDescription', 'genres', 'people'])
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?Movie
    {
        return Movie::with(['descriptions', 'defaultDescription'])
            ->where('slug', $slug)
            ->first();
    }
}


