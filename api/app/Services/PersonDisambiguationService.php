<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use Illuminate\Support\Str;

class PersonDisambiguationService
{
    public function __construct(private readonly PersonRepository $personRepository) {}

    public function determineMeta(Person $person, string $slug): ?array
    {
        $parsed = Person::parseSlug($slug);

        if ($parsed['birth_year'] !== null) {
            return null;
        }

        $allPeople = $this->personRepository->findAllByNameSlug(Str::slug($parsed['name']));

        if ($allPeople->count() <= 1) {
            return null;
        }

        return [
            'ambiguous' => true,
            'message' => 'Multiple people found with this name. Showing most recent by birth date. Use slug with birth year (e.g., "john-smith-1960") for specific version.',
            'alternatives' => $allPeople->map(function (Person $person) {
                return [
                    'slug' => $person->slug,
                    'name' => $person->name,
                    'birth_date' => $person->birth_date?->format('Y-m-d'),
                    'url' => url("/api/v1/people/{$person->slug}"),
                ];
            })->toArray(),
        ];
    }
}
