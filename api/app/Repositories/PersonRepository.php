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
                $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ["%$query%"])
                    ->orWhereRaw('LOWER(birthplace) LIKE LOWER(?)', ["%$query%"])
                    ->orWhereHas('movies', function ($qm) use ($query) {
                        $qm->whereRaw('LOWER(title) LIKE LOWER(?)', ["%$query%"]);
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
     * Handles ambiguous slugs (same name, different people) by searching by name.
     * Also handles cases where request slug doesn't have year but database slug does.
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

        // Parse slug to extract name, birth year, and birthplace
        $parsed = Person::parseSlug($slug);
        $nameSlug = Str::slug($parsed['name']);

        // If slug from request doesn't contain year or suffix, try to find by name only
        // This handles ambiguous slugs and cases where job generated slug with year
        // but request slug doesn't have year
        if ($parsed['birth_year'] === null && $parsed['suffix'] === null) {
            // Return most recent person with matching name slug
            // This will find people like "keanu-reeves-1964" even if request slug is "keanu-reeves"
            return Person::with('bios')
                ->whereRaw('slug LIKE ?', ["{$nameSlug}%"])
                ->orderBy('birth_date', 'desc') // Return most recent by birth date
                ->first();
        }

        // If slug from request HAS year, check if person exists with same name + birth year
        // This handles cases where slug format differs (e.g., "keanu-reeves" vs "keanu-reeves-1964")
        // but represents the same person
        if ($parsed['birth_year'] !== null) {
            // Check if person exists with same name and birth year (even if slug format differs)
            // Match by slug pattern that includes the birth year
            $person = Person::with('bios')
                ->whereRaw('slug LIKE ?', ["{$nameSlug}-{$parsed['birth_year']}%"])
                ->whereNotNull('birth_date') // Only match if birth_date is set
                ->whereYear('birth_date', $parsed['birth_year'])
                ->first();
            if ($person) {
                return $person;
            }
        }

        return null;
    }
}
