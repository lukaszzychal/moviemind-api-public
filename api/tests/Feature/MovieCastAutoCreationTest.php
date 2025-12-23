<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Jobs\RealGenerateMovieJob;
use App\Models\Movie;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MovieCastAutoCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_movie_generation_creates_director_person(): void
    {
        // Use unique slug and title to avoid conflicts with seeded data
        $uniqueSlug = 'test-movie-'.time().'-1999';
        $uniqueTitle = 'Test Movie '.time();

        $fake = $this->fakeOpenAiClient();
        $fake->setMovieResponse($uniqueSlug, [
            'success' => true,
            'title' => $uniqueTitle,
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
            'description' => 'A computer hacker learns about the true nature of reality.',
            'genres' => ['Action', 'Sci-Fi'],
            'cast' => [
                [
                    'name' => 'Lana Wachowski',
                    'role' => 'DIRECTOR',
                ],
            ],
        ]);

        $initialPeopleCount = Person::count();
        $initialMoviePersonCount = \DB::table('movie_person')->count();

        $job = new RealGenerateMovieJob($uniqueSlug, 'test-job-1');
        $job->handle($fake);

        $this->assertDatabaseCount('people', $initialPeopleCount + 1);
        $this->assertDatabaseCount('movie_person', $initialMoviePersonCount + 1);

        $person = Person::where('name', 'Lana Wachowski')->first();
        $this->assertNotNull($person);
        $this->assertEquals('Lana Wachowski', $person->name);

        $movie = Movie::where('title', $uniqueTitle)->where('release_year', 1999)->first();
        $this->assertNotNull($movie);
        $this->assertEquals(1, $movie->people()->wherePivot('role', RoleType::DIRECTOR->value)->count());
    }

    public function test_movie_generation_creates_actors(): void
    {
        $uniqueSlug = 'test-movie-actors-'.time().'-1999';
        $uniqueTitle = 'Test Movie Actors '.time();

        // Use unique actor names to avoid conflicts with seeded data
        $actor1Name = 'Test Actor One '.time();
        $actor2Name = 'Test Actor Two '.time();

        $fake = $this->fakeOpenAiClient();
        $fake->setMovieResponse($uniqueSlug, [
            'success' => true,
            'title' => $uniqueTitle,
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
            'description' => 'A computer hacker learns about the true nature of reality.',
            'genres' => ['Action', 'Sci-Fi'],
            'cast' => [
                [
                    'name' => $actor1Name,
                    'role' => 'ACTOR',
                    'character_name' => 'Neo',
                    'billing_order' => 1,
                ],
                [
                    'name' => $actor2Name,
                    'role' => 'ACTOR',
                    'character_name' => 'Morpheus',
                    'billing_order' => 2,
                ],
            ],
        ]);

        $initialPeopleCount = Person::count();
        $initialMoviePersonCount = \DB::table('movie_person')->count();

        $job = new RealGenerateMovieJob($uniqueSlug, 'test-job-2');
        $job->handle($fake);

        $this->assertDatabaseCount('people', $initialPeopleCount + 2);
        $this->assertDatabaseCount('movie_person', $initialMoviePersonCount + 2);

        $movie = Movie::where('title', $uniqueTitle)->where('release_year', 1999)->first();
        $this->assertNotNull($movie);

        $actors = $movie->people()->wherePivot('role', RoleType::ACTOR->value)->get();
        $this->assertEquals(2, $actors->count());

        $actor1 = $actors->firstWhere('name', $actor1Name);
        $this->assertNotNull($actor1);
        $this->assertEquals('Neo', $actor1->pivot->character_name);
        $this->assertEquals(1, $actor1->pivot->billing_order);

        $actor2 = $actors->firstWhere('name', $actor2Name);
        $this->assertNotNull($actor2);
        $this->assertEquals('Morpheus', $actor2->pivot->character_name);
        $this->assertEquals(2, $actor2->pivot->billing_order);
    }

    public function test_movie_generation_handles_existing_person(): void
    {
        $uniqueSlug = 'test-movie-existing-'.time().'-1999';
        $uniqueTitle = 'Test Movie Existing '.time();
        $personName = 'Existing Person '.time();

        // Create existing person
        $existingPerson = Person::create([
            'name' => $personName,
            'slug' => \Illuminate\Support\Str::slug($personName).'-'.time(),
            'birth_date' => '1964-09-02',
        ]);

        $fake = $this->fakeOpenAiClient();
        $fake->setMovieResponse($uniqueSlug, [
            'success' => true,
            'title' => $uniqueTitle,
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
            'description' => 'A computer hacker learns about the true nature of reality.',
            'genres' => ['Action', 'Sci-Fi'],
            'cast' => [
                [
                    'name' => $personName,
                    'role' => 'ACTOR',
                    'character_name' => 'Neo',
                    'billing_order' => 1,
                ],
            ],
        ]);

        $initialPeopleCount = Person::count();
        $initialMoviePersonCount = \DB::table('movie_person')->count();

        $job = new RealGenerateMovieJob($uniqueSlug, 'test-job-3');
        $job->handle($fake);

        // Should still be same count (reused existing person)
        $this->assertDatabaseCount('people', $initialPeopleCount);
        $this->assertDatabaseCount('movie_person', $initialMoviePersonCount + 1);

        $movie = Movie::where('title', $uniqueTitle)->where('release_year', 1999)->first();
        $this->assertNotNull($movie);

        $person = $movie->people()->first();
        $this->assertEquals($existingPerson->id, $person->id);
        $this->assertEquals('Neo', $person->pivot->character_name);
    }

    public function test_movie_generation_creates_director_and_actors(): void
    {
        $uniqueSlug = 'test-movie-all-'.time().'-1999';
        $uniqueTitle = 'Test Movie All '.time();
        $directorName = 'Test Director '.time();
        $actorName = 'Test Actor '.time();

        $fake = $this->fakeOpenAiClient();
        $fake->setMovieResponse($uniqueSlug, [
            'success' => true,
            'title' => $uniqueTitle,
            'release_year' => 1999,
            'director' => $directorName,
            'description' => 'A computer hacker learns about the true nature of reality.',
            'genres' => ['Action', 'Sci-Fi'],
            'cast' => [
                [
                    'name' => $directorName,
                    'role' => 'DIRECTOR',
                ],
                [
                    'name' => $actorName,
                    'role' => 'ACTOR',
                    'character_name' => 'Neo',
                    'billing_order' => 1,
                ],
            ],
        ]);

        $initialPeopleCount = Person::count();
        $initialMoviePersonCount = \DB::table('movie_person')->count();

        $job = new RealGenerateMovieJob($uniqueSlug, 'test-job-4');
        $job->handle($fake);

        $this->assertDatabaseCount('people', $initialPeopleCount + 2);
        $this->assertDatabaseCount('movie_person', $initialMoviePersonCount + 2);

        $movie = Movie::where('title', $uniqueTitle)->where('release_year', 1999)->first();
        $this->assertNotNull($movie);

        $director = $movie->people()->wherePivot('role', RoleType::DIRECTOR->value)->first();
        $this->assertNotNull($director);
        $this->assertEquals($directorName, $director->name);

        $actor = $movie->people()->wherePivot('role', RoleType::ACTOR->value)->first();
        $this->assertNotNull($actor);
        $this->assertEquals($actorName, $actor->name);
        $this->assertEquals('Neo', $actor->pivot->character_name);
    }
}
