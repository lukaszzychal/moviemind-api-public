<?php

namespace App\Repositories;

use App\Models\Person;
use Illuminate\Support\Collection;

class PersonRepository
{
    public function searchPeople(?string $query, int $limit = 50): Collection
    {
        return Person::query()
            ->when($query, function ($builder) use ($query) {
                $builder->where('name', 'ILIKE', "%$query%")
                    ->orWhere('birthplace', 'ILIKE', "%$query%")
                    ->orWhereHas('movies', function ($qm) use ($query) {
                        $qm->where('title', 'ILIKE', "%$query%");
                    });
            })
            ->with(['defaultBio', 'movies'])
            ->withCount('bios')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?Person
    {
        return Person::with(['bios', 'defaultBio', 'movies'])
            ->withCount('bios')
            ->where('slug', $slug)
            ->first();
    }
}
