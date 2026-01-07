<?php

use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate existing movie titles and directors to movie_locales table.
     * Creates en-US locale entries for all existing movies.
     * This ensures backward compatibility and provides default locale data.
     */
    public function up(): void
    {
        // Check if movie_locales table exists
        if (! Schema::hasTable('movie_locales')) {
            return; // Skip if table doesn't exist yet
        }

        // Get all movies that don't have en-US locale yet
        $movies = Movie::whereDoesntHave('locales', function ($query) {
            $query->where('locale', 'en-US');
        })->get();

        // Create en-US locale for each movie
        foreach ($movies as $movie) {
            // Check if locale already exists (race condition protection)
            $existing = MovieLocale::where('movie_id', $movie->id)
                ->where('locale', 'en-US')
                ->first();

            if ($existing) {
                continue; // Skip if already exists
            }

            MovieLocale::create([
                'movie_id' => $movie->id,
                'locale' => Locale::EN_US,
                'title_localized' => $movie->title,
                'director_localized' => $movie->director,
            ]);
        }
    }

    /**
     * Reverse the migration by removing en-US locales created from existing titles.
     * Note: This only removes locales that match the original title/director,
     * to avoid removing manually created locales.
     */
    public function down(): void
    {
        if (! Schema::hasTable('movie_locales')) {
            return;
        }

        // Remove en-US locales that match original movie title and director
        // This is a best-effort rollback - manually created locales may also be removed
        // Use raw query for complex whereColumn conditions
        DB::table('movie_locales')
            ->join('movies', 'movie_locales.movie_id', '=', 'movies.id')
            ->where('movie_locales.locale', 'en-US')
            ->whereColumn('movies.title', 'movie_locales.title_localized')
            ->where(function ($query) {
                $query->whereColumn('movies.director', 'movie_locales.director_localized')
                    ->orWhere(function ($q) {
                        $q->whereNull('movies.director')
                            ->whereNull('movie_locales.director_localized');
                    });
            })
            ->delete(['movie_locales.*']);
    }
};
