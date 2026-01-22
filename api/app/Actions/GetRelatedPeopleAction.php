<?php

namespace App\Actions;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Services\HateoasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetRelatedPeopleAction
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly HateoasService $hateoas
    ) {}

    public function handle(Person $person, Request $request): array
    {
        // Parse type filter: collaborators, same_name, or all (default)
        $typeFilter = $request->query('type', 'all');
        $typeFilter = strtolower((string) $typeFilter);

        // Parse collaborator role filter
        $collaboratorRole = $request->query('collaborator_role');
        if ($collaboratorRole !== null) {
            $collaboratorRole = strtoupper((string) $collaboratorRole);
            // Validate role
            if (! in_array($collaboratorRole, ['ACTOR', 'DIRECTOR', 'WRITER', 'PRODUCER'], true)) {
                throw new \InvalidArgumentException('Invalid collaborator_role. Must be one of: ACTOR, DIRECTOR, WRITER, PRODUCER');
                // The controller handles the error formatting catch, or we can let exception handler handle it.
                // For now, let's assume we return a special result or throw.
                // To keep it simple and consistent with previous flow, we might need to validate in controller or here.
                // Best practice: Validation in FormRequest. Since we don't have one for GET, we validate here.
            }
        }

        // Parse limit
        $limit = (int) $request->query('limit', 20);
        $limit = max(1, min(100, $limit));

        // Build cache key
        $cacheKey = $this->buildRelatedCacheKey($person->slug, $typeFilter, $collaboratorRole, $limit);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($person, $typeFilter, $collaboratorRole, $limit) {
            $collaborators = [];
            $sameName = [];

            // Collaborators
            if ($typeFilter === 'all' || $typeFilter === 'collaborators') {
                $collaborators = $this->getCollaborators($person, $collaboratorRole, $limit);
            }

            // Same Name
            if ($typeFilter === 'all' || $typeFilter === 'same_name') {
                $sameName = $this->getSameNamePeople($person, $limit);
            }

            $allRelatedPeople = array_merge($collaborators, $sameName);

            return [
                'person' => [
                    'id' => $person->id,
                    'slug' => $person->slug,
                    'name' => $person->name,
                ],
                'related_people' => $allRelatedPeople,
                'count' => count($allRelatedPeople),
                'filters' => [
                    'type' => $typeFilter,
                    'collaborator_role' => $collaboratorRole,
                    'collaborators_count' => count($collaborators),
                    'same_name_count' => count($sameName),
                ],
                '_links' => [
                    'self' => ['href' => url("/api/v1/people/{$person->slug}/related")],
                    'person' => ['href' => url("/api/v1/people/{$person->slug}")],
                    'collaborators' => ['href' => url("/api/v1/people/{$person->slug}/related?type=collaborators")],
                    'same_name' => ['href' => url("/api/v1/people/{$person->slug}/related?type=same_name")],
                ],
            ];
        });
    }

    private function getCollaborators(Person $person, ?string $collaboratorRole, int $limit): array
    {
        $personMovies = $person->movies()->pluck('movies.id');

        if ($personMovies->isEmpty()) {
            return [];
        }

        $queryBuilder = Person::whereHas('movies', function ($q) use ($personMovies, $person, $collaboratorRole) {
            $q->whereIn('movies.id', $personMovies)
                ->where('movie_person.person_id', '!=', $person->id);

            if ($collaboratorRole !== null) {
                $q->where('movie_person.role', $collaboratorRole);
            }
        })
            ->with(['movies' => function ($query) use ($personMovies) {
                $query->whereIn('movies.id', $personMovies)
                    ->withPivot(['role', 'character_name', 'job']);
            }]);

        $query = $queryBuilder->get();

        $collaboratorsMap = [];
        foreach ($query as $collaborator) {
            $collaborations = [];
            $personRole = null;

            foreach ($collaborator->movies as $movie) {
                $moviePivot = $movie->pivot;
                $personMoviePivot = $person->movies()->where('movies.id', $movie->id)->first()?->pivot;

                $personRoleInMovie = $personMoviePivot !== null ? $personMoviePivot->role : null;
                $collaboratorRoleInMovie = $moviePivot !== null ? $moviePivot->role : null;

                if ($personRoleInMovie !== $collaboratorRoleInMovie) {
                    $collaborations[] = [
                        'movie_id' => $movie->id,
                        'movie_slug' => $movie->slug,
                        'movie_title' => $movie->title ?? '',
                        'person_role' => $personRoleInMovie,
                        'collaborator_role' => $collaboratorRoleInMovie,
                    ];

                    if ($personRole === null) {
                        $personRole = $personRoleInMovie;
                    }
                }
            }

            if (! empty($collaborations)) {
                $collaboratorsMap[$collaborator->id] = [
                    'person' => $collaborator,
                    'collaborations' => $collaborations,
                    'collaborations_count' => count($collaborations),
                    'person_role' => $personRole,
                ];
            }
        }

        uasort($collaboratorsMap, function (array $a, array $b): int {
            return $b['collaborations_count'] <=> $a['collaborations_count'];
        });

        $collaboratorsMap = array_slice($collaboratorsMap, 0, $limit, true);

        $result = [];
        foreach ($collaboratorsMap as $data) {
            $collaborator = $data['person'];
            $collaborations = $data['collaborations'];
            $collaboratorRoleInFirst = $collaborations[0]['collaborator_role'] ?? null;

            $result[] = [
                'id' => $collaborator->id,
                'slug' => $collaborator->slug,
                'name' => $collaborator->name,
                'relationship_type' => 'COLLABORATOR',
                'relationship_label' => $collaboratorRoleInFirst !== null
                    ? "Collaborator ({$collaboratorRoleInFirst})"
                    : 'Collaborator',
                'collaborations' => $collaborations,
                'collaborations_count' => count($collaborations),
                '_links' => [
                    'self' => ['href' => url("/api/v1/people/{$collaborator->slug}")],
                ],
            ];
        }

        return $result;
    }

    private function getSameNamePeople(Person $person, int $limit): array
    {
        $parsed = Person::parseSlug($person->slug);
        $baseSlug = \Illuminate\Support\Str::slug($parsed['name']);

        $sameNamePeople = $this->personRepository->findAllByNameSlug($baseSlug)
            ->where('id', '!=', $person->id)
            ->take($limit);

        $result = [];
        foreach ($sameNamePeople as $sameNamePerson) {
            $result[] = [
                'id' => $sameNamePerson->id,
                'slug' => $sameNamePerson->slug,
                'name' => $sameNamePerson->name,
                'birth_date' => $sameNamePerson->birth_date?->format('Y-m-d'),
                'birthplace' => $sameNamePerson->birthplace,
                'relationship_type' => 'SAME_NAME',
                'relationship_label' => 'Same Name',
                '_links' => [
                    'self' => ['href' => url("/api/v1/people/{$sameNamePerson->slug}")],
                ],
            ];
        }

        return $result;
    }

    private function buildRelatedCacheKey(string $slug, string $typeFilter, ?string $collaboratorRole, int $limit): string
    {
        $parts = ['person_related', $slug, $typeFilter];
        if ($collaboratorRole !== null) {
            $parts[] = $collaboratorRole;
        }
        $parts[] = "limit_{$limit}";

        return implode(':', $parts);
    }
}
