<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MovieLocale;

class MovieLocaleRepository
{
    /**
     * Find a movie locale by movie ID and locale code.
     *
     * @param  string  $movieId  Movie UUID
     * @param  string  $locale  Locale code (e.g., 'pl-PL', 'en-US')
     */
    public function findByMovieIdAndLocale(string $movieId, string $locale): ?MovieLocale
    {
        return MovieLocale::where('movie_id', $movieId)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Create a new movie locale.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MovieLocale
    {
        return MovieLocale::create($data);
    }

    /**
     * Update an existing movie locale.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MovieLocale $locale, array $data): MovieLocale
    {
        $locale->update($data);
        $locale->refresh();

        return $locale;
    }
}
