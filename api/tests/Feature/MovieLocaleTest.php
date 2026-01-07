<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_create_movie_locale(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Creating a movie locale
        $locale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
            'tagline' => 'Świat się zmienił',
            'synopsis' => 'Opis filmu Matrix po polsku',
        ]);

        // THEN: Movie locale should be created with correct data
        $this->assertDatabaseHas('movie_locales', [
            'movie_id' => $movie->id,
            'locale' => 'pl-PL',
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
        ]);

        $this->assertEquals('pl-PL', $locale->locale->value);
        $this->assertEquals('Matrix', $locale->title_localized);
        $this->assertEquals('Wachowscy', $locale->director_localized);
    }

    public function test_movie_locale_belongs_to_movie(): void
    {
        // GIVEN: A movie and its locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $locale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
        ]);

        // WHEN: Accessing movie from locale
        $relatedMovie = $locale->movie;

        // THEN: Should return the correct movie
        $this->assertNotNull($relatedMovie);
        $this->assertEquals($movie->id, $relatedMovie->id);
        $this->assertEquals('The Matrix', $relatedMovie->title);
    }

    public function test_movie_has_many_locales(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Creating multiple locales for the movie
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

        $localeDe = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::DE_DE,
            'title_localized' => 'Matrix',
        ]);

        // THEN: Movie should have all locales
        $locales = $movie->locales;
        $this->assertCount(3, $locales);
        $this->assertTrue($locales->contains($localePl));
        $this->assertTrue($locales->contains($localeEn));
        $this->assertTrue($locales->contains($localeDe));
    }

    public function test_movie_locale_requires_unique_movie_id_and_locale(): void
    {
        // GIVEN: A movie with a locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
        ]);

        // WHEN: Trying to create duplicate locale for the same movie
        // THEN: Should throw unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix Duplicate',
        ]);
    }

    public function test_can_retrieve_movie_locale_by_locale(): void
    {
        // GIVEN: A movie with multiple locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
        ]);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'title_localized' => 'The Matrix',
        ]);

        // WHEN: Retrieving locale by specific locale
        $localePl = $movie->locale('pl-PL');
        $localeEn = $movie->locale('en-US');
        $localeDe = $movie->locale('de-DE');

        // THEN: Should return correct locale or null
        $this->assertNotNull($localePl);
        $this->assertEquals('pl-PL', $localePl->locale->value);
        $this->assertEquals('Matrix', $localePl->title_localized);

        $this->assertNotNull($localeEn);
        $this->assertEquals('en-US', $localeEn->locale->value);
        $this->assertEquals('The Matrix', $localeEn->title_localized);

        $this->assertNull($localeDe);
    }

    public function test_get_localized_title_with_fallback(): void
    {
        // GIVEN: A movie with only English locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'title_localized' => 'The Matrix',
        ]);

        // WHEN: Getting localized title for Polish (not exists)
        $titlePl = $movie->getLocalizedTitle('pl-PL');
        $titleEn = $movie->getLocalizedTitle('en-US');

        // THEN: Should fallback to English for Polish, return English for English
        $this->assertEquals('The Matrix', $titlePl);
        $this->assertEquals('The Matrix', $titleEn);
    }

    public function test_get_localized_title_returns_null_when_no_locale_exists(): void
    {
        // GIVEN: A movie without any locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Getting localized title
        $title = $movie->getLocalizedTitle('pl-PL');

        // THEN: Should return null (no fallback when no locales exist)
        $this->assertNull($title);
    }

    public function test_movie_locale_has_uuid_primary_key(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create();

        // WHEN: Creating a movie locale
        $locale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Test',
        ]);

        // THEN: ID should be a valid UUID
        $this->assertIsString($locale->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $locale->id
        );
    }

    public function test_movie_locale_can_have_nullable_fields(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create();

        // WHEN: Creating a movie locale with only required fields
        $locale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
        ]);

        // THEN: Should be created successfully with null optional fields
        $this->assertNotNull($locale);
        $this->assertNull($locale->title_localized);
        $this->assertNull($locale->director_localized);
        $this->assertNull($locale->tagline);
        $this->assertNull($locale->synopsis);
    }
}
