<?php

namespace App\Repositories;

use App\Models\Person;
use Illuminate\Support\Collection;

class PersonRepository
{
    public function searchPeople(?string $query, ?string $role = null, int $limit = 50): Collection
    {
        return Person::query()
            ->when($query, function ($builder) use ($query) {
                $builder->where('name', 'ILIKE', "%$query%")
                    ->orWhere('birthplace', 'ILIKE', "%$query%");
            })
            ->when($role, function ($builder) use ($role) {
                $builder->whereHas('movies', function ($q) use ($role) {
                    $q->where('movie_person.role', $role);
                });
            })
            ->with(['defaultBio', 'movies' => function ($query) use ($role) {
                if ($role) {
                    $query->wherePivot('role', $role)
                        ->orderBy('movie_person.billing_order');
                }
            }])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function findBySlugWithRelations(string $slug): ?Person
    {
        return Person::with(['bios', 'defaultBio', 'movies'])
            ->where('slug', $slug)
            ->first();
    }
}
