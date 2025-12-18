<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RoleType;
use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize movie metadata (actors, crew) from TMDb.
 * This job runs asynchronously after a movie is created.
 */
class SyncMovieMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $movieId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncMovieMetadataJob started', [
            'movie_id' => $this->movieId,
            'attempt' => $this->attempts(),
        ]);

        $movie = Movie::find($this->movieId);
        if (! $movie) {
            Log::warning('SyncMovieMetadataJob: Movie not found', [
                'movie_id' => $this->movieId,
            ]);

            return;
        }

        // Find TMDb snapshot
        $snapshot = TmdbSnapshot::where('entity_type', 'MOVIE')
            ->where('entity_id', $movie->id)
            ->first();

        if (! $snapshot) {
            Log::info('SyncMovieMetadataJob: No TMDb snapshot found, skipping', [
                'movie_id' => $this->movieId,
            ]);

            return;
        }

        $tmdbData = $snapshot->raw_data;
        if (empty($tmdbData) || empty($tmdbData['credits'])) {
            Log::info('SyncMovieMetadataJob: No credits data in snapshot, skipping', [
                'movie_id' => $this->movieId,
            ]);

            return;
        }

        // Sync cast (actors)
        if (! empty($tmdbData['credits']['cast'])) {
            $this->syncCast($movie, $tmdbData['credits']['cast']);
        }

        // Sync crew (directors, writers, producers)
        if (! empty($tmdbData['credits']['crew'])) {
            $this->syncCrew($movie, $tmdbData['credits']['crew']);
        }

        Log::info('SyncMovieMetadataJob completed', [
            'movie_id' => $this->movieId,
            'people_count' => $movie->fresh()->people->count(),
        ]);
    }

    /**
     * Sync cast (actors) from TMDb data.
     *
     * @param  array<int, array{id?: int|null, name?: string|null, character?: string|null, order?: int|null}>  $cast
     */
    private function syncCast(Movie $movie, array $cast): void
    {
        foreach ($cast as $actorData) {
            $tmdbId = $actorData['id'] ?? null;
            $name = $actorData['name'] ?? '';
            $character = $actorData['character'] ?? null;
            $order = $actorData['order'] ?? null;

            if (! $name) {
                continue;
            }

            // Find or create person
            $person = $this->findOrCreatePerson($name, $tmdbId);

            // Attach to movie if not already attached as ACTOR
            if (! $movie->people()->where('person_id', $person->id)->where('role', RoleType::ACTOR->value)->exists()) {
                $movie->people()->attach($person->id, [
                    'role' => RoleType::ACTOR->value,
                    'character_name' => $character,
                    'billing_order' => $order,
                ]);
            }
        }
    }

    /**
     * Sync crew (directors, writers, producers) from TMDb data.
     *
     * @param  array<int, array{id?: int|null, name?: string|null, job?: string|null}>  $crew
     */
    private function syncCrew(Movie $movie, array $crew): void
    {
        // Map TMDb jobs to our role types
        $jobToRole = [
            'Director' => RoleType::DIRECTOR,
            'Writer' => RoleType::WRITER,
            'Screenplay' => RoleType::WRITER,
            'Producer' => RoleType::PRODUCER,
            'Executive Producer' => RoleType::PRODUCER,
        ];

        foreach ($crew as $crewMember) {
            $tmdbId = $crewMember['id'] ?? null;
            $name = $crewMember['name'] ?? '';
            $job = $crewMember['job'] ?? '';

            if (! $name || ! $job) {
                continue;
            }

            // Map job to role
            $role = $jobToRole[$job] ?? null;
            if (! $role) {
                continue; // Skip unknown job types
            }

            // Find or create person
            $person = $this->findOrCreatePerson($name, $tmdbId);

            // Attach to movie if not already attached with this role
            if (! $movie->people()->where('person_id', $person->id)->where('role', $role->value)->exists()) {
                $movie->people()->attach($person->id, [
                    'role' => $role->value,
                    'job' => $job,
                ]);
            }
        }
    }

    /**
     * Find or create a Person from name and optional TMDb ID.
     */
    private function findOrCreatePerson(string $name, ?int $tmdbId = null): Person
    {
        // Try to find by TMDb ID first (most reliable)
        if ($tmdbId !== null) {
            $person = Person::where('tmdb_id', $tmdbId)->first();
            if ($person) {
                return $person;
            }
        }

        // Try to find by name (may have duplicates, but better than nothing)
        $person = Person::where('name', $name)->first();
        if ($person) {
            // Update TMDb ID if we have it
            if ($tmdbId !== null && ! $person->tmdb_id) {
                $person->update(['tmdb_id' => $tmdbId]);
            }

            return $person;
        }

        // Create new person
        $slug = Person::generateSlug($name);
        $person = Person::create([
            'name' => $name,
            'slug' => $slug,
            'tmdb_id' => $tmdbId,
        ]);

        Log::info('SyncMovieMetadataJob: Created new person', [
            'person_id' => $person->id,
            'name' => $name,
            'tmdb_id' => $tmdbId,
        ]);

        return $person;
    }
}
