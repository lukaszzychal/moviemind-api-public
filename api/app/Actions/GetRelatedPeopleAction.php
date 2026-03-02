<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Person;
use App\Repositories\PersonRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Action for retrieving related people for a given person.
 *
 * Supports filtering by relationship type:
 * - ?type=collaborators - Only collaborators (people who worked together in same movies, different roles)
 * - ?type=same_name - Only people with same name (disambiguation)
 * - ?type=all or no filter - Both collaborators and same name
 *
 * Additional filters:
 * - ?collaborator_role=ACTOR|DIRECTOR|WRITER|PRODUCER - Filter collaborators by role
 * - ?limit=10 - Limit results (default: 20)
 */
class GetRelatedPeopleAction
{
    private const ALLOWED_ROLES = ['ACTOR', 'DIRECTOR', 'WRITER', 'PRODUCER'];

    public function __construct(
        private readonly PersonRepository $personRepository
    ) {}

    /**
     * Handle the action.
     *
     * @param  Person  $person  The person to get related people for
     * @param  Request  $request  HTTP request with optional type, collaborator_role, limit
     * @return array Response data with person, related_people, count, filters, and _links
     *
     * @throws \InvalidArgumentException If invalid type or collaborator_role is provided
     */
    public function handle(Person $person, Request $request): array
    {
        $typeFilter = $this->normalizeTypeFilter($request->query('type'));
        $collaboratorRole = $this->normalizeCollaboratorRole($request->query('collaborator_role'));
        $limit = $this->normalizeLimit($request->query('limit'));

        $collaborators = $this->getCollaborators($person, $typeFilter, $collaboratorRole, $limit);
        $collaboratorsCount = $collaborators->count();

        $sameName = $this->getSameName($person, $typeFilter, $limit);
        $sameNameCount = $sameName->count();

        $relatedPeople = $typeFilter === 'all'
            ? $collaborators->concat($sameName)->take($limit)->values()->all()
            : ($typeFilter === 'collaborators' ? $collaborators->values()->all() : $sameName->values()->all());

        $filters = [
            'type' => $typeFilter,
            'collaborators_count' => $collaboratorsCount,
            'same_name_count' => $sameNameCount,
        ];

        $links = [
            'self' => ['href' => url("/api/v1/people/{$person->slug}/related")],
            'person' => ['href' => url("/api/v1/people/{$person->slug}")],
            'collaborators' => ['href' => url("/api/v1/people/{$person->slug}/related?type=collaborators")],
            'same_name' => ['href' => url("/api/v1/people/{$person->slug}/related?type=same_name")],
        ];

        return [
            'person' => [
                'id' => $person->id,
                'slug' => $person->slug,
                'name' => $person->name,
            ],
            'related_people' => $relatedPeople,
            'count' => count($relatedPeople),
            'filters' => $filters,
            '_links' => $links,
        ];
    }

    private function normalizeTypeFilter(mixed $type): string
    {
        if ($type === null || $type === '') {
            return 'all';
        }
        $typeLower = strtolower((string) $type);

        return match ($typeLower) {
            'collaborators', 'same_name', 'all' => $typeLower,
            default => throw new \InvalidArgumentException("Invalid type filter: {$type}. Allowed values: collaborators, same_name, all"),
        };
    }

    /**
     * @return string|null Allowed role or null (any)
     *
     * @throws \InvalidArgumentException
     */
    private function normalizeCollaboratorRole(mixed $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }
        $roleUpper = strtoupper((string) $role);
        if (! in_array($roleUpper, self::ALLOWED_ROLES, true)) {
            throw new \InvalidArgumentException('Invalid collaborator_role. Allowed values: ACTOR, DIRECTOR, WRITER, PRODUCER');
        }

        return $roleUpper;
    }

    private function normalizeLimit(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return 20;
        }
        $n = (int) $limit;

        return $n >= 1 && $n <= 100 ? $n : 20;
    }

    /**
     * Get collaborators: people who worked on same movies with different role.
     * Sorted by collaborations count descending. Excludes same role.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getCollaborators(Person $person, string $typeFilter, ?string $roleFilter, int $limit): \Illuminate\Support\Collection
    {
        if ($typeFilter === 'same_name') {
            return collect();
        }

        $personPivots = \DB::table('movie_person')
            ->where('person_id', $person->id)
            ->get();

        $movieIds = $personPivots->pluck('movie_id')->unique()->values()->all();
        if (empty($movieIds)) {
            return collect();
        }

        $personRolesByMovie = $personPivots->groupBy('movie_id')->map(fn ($group) => $group->pluck('role')->unique()->values()->all());

        $movies = \App\Models\Movie::whereIn('id', $movieIds)->get()->keyBy('id');

        $query = \DB::table('movie_person')
            ->whereIn('movie_id', $movieIds)
            ->where('person_id', '!=', $person->id);

        if ($roleFilter !== null) {
            $query->where('role', $roleFilter);
        }

        $rows = $query->get();

        $byPerson = [];
        foreach ($rows as $row) {
            $movieId = $row->movie_id;
            $personRoles = $personRolesByMovie->get($movieId, []);
            if ($roleFilter === null && in_array($row->role, $personRoles, true)) {
                continue;
            }
            $collaboratorId = $row->person_id;
            if (! isset($byPerson[$collaboratorId])) {
                $byPerson[$collaboratorId] = [
                    'person_id' => $collaboratorId,
                    'collaborations' => [],
                    'count' => 0,
                ];
            }
            $movie = $movies->get($movieId);
            $personRoleOnMovie = $personPivots->where('movie_id', $movieId)->first()?->role ?? 'UNKNOWN';
            $byPerson[$collaboratorId]['collaborations'][] = [
                'movie_id' => $movieId,
                'movie_slug' => $movie?->slug ?? '',
                'movie_title' => $movie?->title ?? '',
                'person_role' => $personRoleOnMovie,
                'collaborator_role' => $row->role,
            ];
            $byPerson[$collaboratorId]['count']++;
        }

        $collaboratorIds = array_keys($byPerson);
        if (empty($collaboratorIds)) {
            return collect();
        }

        $people = Person::whereIn('id', $collaboratorIds)->get()->keyBy('id');

        $result = collect($byPerson)
            ->sortByDesc('count')
            ->take($limit)
            ->map(function ($data) use ($people) {
                $p = $people->get($data['person_id']);
                if (! $p) {
                    return null;
                }

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                    'relationship_type' => 'COLLABORATOR',
                    'relationship_label' => 'Collaborator',
                    'collaborations' => $data['collaborations'],
                    'collaborations_count' => $data['count'],
                    '_links' => [
                        'self' => ['href' => url("/api/v1/people/{$p->slug}")],
                    ],
                ];
            })
            ->filter()
            ->values();

        return $result;
    }

    /**
     * Get people with same name (disambiguation). Excludes the person itself.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getSameName(Person $person, string $typeFilter, int $limit): \Illuminate\Support\Collection
    {
        if ($typeFilter === 'collaborators') {
            return collect();
        }

        $parsed = Person::parseSlug($person->slug);
        $baseSlug = Str::slug($parsed['name']);

        $sameNamePeople = $this->personRepository->findAllByNameSlug($baseSlug)
            ->reject(fn (Person $p) => $p->id === $person->id)
            ->take($limit);

        return $sameNamePeople->map(function (Person $p) {
            return [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'birth_date' => $p->birth_date?->format('Y-m-d'),
                'birthplace' => $p->birthplace,
                'relationship_type' => 'SAME_NAME',
                'relationship_label' => 'Same Name',
                '_links' => [
                    'self' => ['href' => url("/api/v1/people/{$p->slug}")],
                ],
            ];
        });
    }
}
