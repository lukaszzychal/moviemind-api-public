<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieLocale;
use App\Repositories\MovieLocaleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieLocaleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private MovieLocaleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->repository = new MovieLocaleRepository;
    }

    public function test_can_find_by_movie_id_and_locale(): void
    {
        // GIVEN: A movie with locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $localePl = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
        ]);

        $localeEn = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'title_localized' => 'The Matrix',
        ]);

        // WHEN: Finding by movie ID and locale
        $foundPl = $this->repository->findByMovieIdAndLocale($movie->id, 'pl-PL');
        $foundEn = $this->repository->findByMovieIdAndLocale($movie->id, 'en-US');
        $foundDe = $this->repository->findByMovieIdAndLocale($movie->id, 'de-DE');

        // THEN: Should return correct locale or null
        $this->assertNotNull($foundPl);
        $this->assertEquals($localePl->id, $foundPl->id);
        $this->assertEquals('pl-PL', $foundPl->locale->value);
        $this->assertEquals('Matrix', $foundPl->title_localized);

        $this->assertNotNull($foundEn);
        $this->assertEquals($localeEn->id, $foundEn->id);
        $this->assertEquals('en-US', $foundEn->locale->value);
        $this->assertEquals('The Matrix', $foundEn->title_localized);

        $this->assertNull($foundDe);
    }

    public function test_returns_null_when_locale_not_found(): void
    {
        // GIVEN: A movie without locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Finding by movie ID and locale that doesn't exist
        $found = $this->repository->findByMovieIdAndLocale($movie->id, 'pl-PL');

        // THEN: Should return null
        $this->assertNull($found);
    }

    public function test_can_create_movie_locale(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $data = [
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
            'tagline' => 'Świat się zmienił',
            'synopsis' => 'Opis filmu Matrix po polsku',
        ];

        // WHEN: Creating a movie locale
        $locale = $this->repository->create($data);

        // THEN: Should be created in database
        $this->assertDatabaseHas('movie_locales', [
            'movie_id' => $movie->id,
            'locale' => 'pl-PL',
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
        ]);

        $this->assertNotNull($locale);
        $this->assertEquals('pl-PL', $locale->locale->value);
        $this->assertEquals('Matrix', $locale->title_localized);
        $this->assertEquals('Wachowscy', $locale->director_localized);
    }

    public function test_can_update_movie_locale(): void
    {
        // GIVEN: A movie locale exists
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $locale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
        ]);

        $updateData = [
            'title_localized' => 'Matrix - Zaktualizowany',
            'director_localized' => 'Lilly i Lana Wachowscy',
            'tagline' => 'Nowy tagline',
        ];

        // WHEN: Updating the locale
        $updated = $this->repository->update($locale, $updateData);

        // THEN: Should be updated in database
        $this->assertDatabaseHas('movie_locales', [
            'movie_id' => $movie->id,
            'locale' => 'pl-PL',
            'title_localized' => 'Matrix - Zaktualizowany',
            'director_localized' => 'Lilly i Lana Wachowscy',
            'tagline' => 'Nowy tagline',
        ]);

        $this->assertEquals('Matrix - Zaktualizowany', $updated->title_localized);
        $this->assertEquals('Lilly i Lana Wachowscy', $updated->director_localized);
        $this->assertEquals('Nowy tagline', $updated->tagline);
    }
}
