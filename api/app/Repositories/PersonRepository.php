<?php

namespace App\Repositories;

use App\Models\Person;

class PersonRepository
{
    public function findBySlugWithRelations(string $slug): ?Person
    {
        return Person::with(['bios', 'defaultBio', 'movies'])
            ->where('slug', $slug)
            ->first();
    }
}


