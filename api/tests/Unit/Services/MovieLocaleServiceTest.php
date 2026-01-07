<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieLocale;
use App\Repositories\MovieLocaleRepository;
use App\Services\MovieLocaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieLocaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private MovieLocaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new MovieLocaleService(new MovieLocaleRepository);
    }

    public function test_get_localized_metadata_returns_locale_when_exists(): void
    {
        // GIVEN: A movie with Polish locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $localePl = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
            'director_localized' => 'Wachowscy',
        ]);

        // WHEN: Getting localized metadata for Polish
        $result = $this->service->getLocalizedMetadata($movie, 'pl-PL');

        // THEN: Should return Polish locale
        $this->assertNotNull($result);
        $this->assertEquals($localePl->id, $result->id);
        $this->assertEquals('pl-PL', $result->locale->value);
        $this->assertEquals('Matrix', $result->title_localized);
    }

    public function test_get_localized_metadata_falls_back_to_en_us(): void
    {
        // GIVEN: A movie with only English locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $localeEn = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'title_localized' => 'The Matrix',
        ]);

        // WHEN: Getting localized metadata for Polish (not exists)
        $result = $this->service->getLocalizedMetadata($movie, 'pl-PL');

        // THEN: Should fallback to English
        $this->assertNotNull($result);
        $this->assertEquals($localeEn->id, $result->id);
        $this->assertEquals('en-US', $result->locale->value);
        $this->assertEquals('The Matrix', $result->title_localized);
    }

    public function test_get_localized_metadata_returns_null_when_no_locale_exists(): void
    {
        // GIVEN: A movie without any locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Getting localized metadata
        $result = $this->service->getLocalizedMetadata($movie, 'pl-PL');

        // THEN: Should return null (no fallback when no locales exist)
        $this->assertNull($result);
    }

    public function test_get_localized_title_with_fallback(): void
    {
        // GIVEN: A movie with English locale only
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
        $titlePl = $this->service->getLocalizedTitle($movie, 'pl-PL');
        $titleEn = $this->service->getLocalizedTitle($movie, 'en-US');

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
        $title = $this->service->getLocalizedTitle($movie, 'pl-PL');

        // THEN: Should return null (no fallback when no locales exist)
        $this->assertNull($title);
    }

    public function test_get_localized_director_with_fallback(): void
    {
        // GIVEN: A movie with English locale only
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'director_localized' => 'Wachowskis',
        ]);

        // WHEN: Getting localized director for Polish (not exists)
        $directorPl = $this->service->getLocalizedDirector($movie, 'pl-PL');
        $directorEn = $this->service->getLocalizedDirector($movie, 'en-US');

        // THEN: Should fallback to English for Polish, return English for English
        $this->assertEquals('Wachowskis', $directorPl);
        $this->assertEquals('Wachowskis', $directorEn);
    }

    public function test_get_localized_director_returns_null_when_no_locale_exists(): void
    {
        // GIVEN: A movie without any locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Getting localized director
        $director = $this->service->getLocalizedDirector($movie, 'pl-PL');

        // THEN: Should return null (no fallback when no locales exist)
        $this->assertNull($director);
    }

    public function test_ensure_locale_exists_creates_when_not_exists(): void
    {
        // GIVEN: A movie without locales
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        // WHEN: Ensuring Polish locale exists
        $locale = $this->service->ensureLocaleExists($movie, 'pl-PL');

        // THEN: Should create new locale
        $this->assertNotNull($locale);
        $this->assertEquals('pl-PL', $locale->locale->value);
        $this->assertEquals($movie->id, $locale->movie_id);
        $this->assertDatabaseHas('movie_locales', [
            'movie_id' => $movie->id,
            'locale' => 'pl-PL',
        ]);
    }

    public function test_ensure_locale_exists_returns_existing_when_exists(): void
    {
        // GIVEN: A movie with Polish locale
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'release_year' => 1999,
        ]);

        $existingLocale = MovieLocale::create([
            'movie_id' => $movie->id,
            'locale' => Locale::PL_PL,
            'title_localized' => 'Matrix',
        ]);

        // WHEN: Ensuring Polish locale exists
        $locale = $this->service->ensureLocaleExists($movie, 'pl-PL');

        // THEN: Should return existing locale (not create new)
        $this->assertNotNull($locale);
        $this->assertEquals($existingLocale->id, $locale->id);
        $this->assertEquals('Matrix', $locale->title_localized);

        // Verify only one locale exists
        $this->assertEquals(1, MovieLocale::where('movie_id', $movie->id)->where('locale', 'pl-PL')->count());
    }
}
