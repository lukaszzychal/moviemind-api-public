<?php

namespace App\Repositories;

use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        // Try exact match first
        $person = Person::with(['bios', 'defaultBio', 'movies'])
            ->withCount('bios')
            ->where('slug', $slug)
            ->first();

        if ($person) {
            return $person;
        }

        // If slug doesn't contain year or suffix, try to find by name only (ambiguous slug handling)
        // This matches the behavior of MovieRepository::findBySlugWithRelations()
        $parsed = Person::parseSlug($slug);
        if ($parsed['birth_year'] === null && $parsed['suffix'] === null) {
            $nameSlug = Str::slug($parsed['name']);

            // Return most recent person by birth date with matching name slug
            return Person::with(['bios', 'defaultBio', 'movies'])
                ->withCount('bios')
                ->whereRaw('slug LIKE ?', ["{$nameSlug}%"])
                ->orderBy('birth_date', 'desc') // Return most recent by birth date
                ->first();
        }

        return null;
    }

    /**
     * Find all people with the same name (different birth dates).
     * Useful for disambiguation when multiple people share a name.
     */
    public function findAllByNameSlug(string $baseSlug): Collection
    {
        return Person::with(['bios', 'defaultBio', 'movies'])
            ->withCount('bios')
            ->whereRaw('slug LIKE ?', ["{$baseSlug}%"])
            ->orderBy('birth_date', 'desc')
            ->get();
    }

    /**
     * Find person by slug for use in Jobs.
     * Handles ambiguous slugs (same name, different people) by searching by last name.
     * Uses lighter relations than findBySlugWithRelations (only 'bios').
     *
     * @param  string  $slug  The slug to search for
     * @param  int|null  $existingId  Optional existing person ID to check first
     */
    public function findBySlugForJob(string $slug, ?int $existingId = null): ?Person
    {
        // If existing ID is provided, try to find by ID first
        if ($existingId !== null) {
            $person = Person::with('bios')->find($existingId);
            if ($person) {
                return $person;
            }
        }

        // Try exact match first
        $person = Person::with('bios')->where('slug', $slug)->first();
        if ($person) {
            return $person;
        }

        // If slug doesn't contain year or suffix, try to find by name only (ambiguous slug handling)
        // This helps when multiple people share the same name
        $parsed = Person::parseSlug($slug);
        if ($parsed['birth_year'] === null && $parsed['suffix'] === null) {
            // Extract last name from slug (assume last word is surname)
            $nameParts = explode('-', $slug);
            if (count($nameParts) > 1) {
                // Try to find by last name (last part of slug)
                $lastName = end($nameParts);
                $nameSlug = Str::slug($parsed['name']);

                // Return first person with matching name slug (or most recent by birth date if available)
                return Person::with('bios')
                    ->whereRaw('slug LIKE ?', ["{$nameSlug}%"])
                    ->orderBy('birth_date', 'desc') // Return most recent by birth date
                    ->first();
            }
        }

        return null;
    }
}
