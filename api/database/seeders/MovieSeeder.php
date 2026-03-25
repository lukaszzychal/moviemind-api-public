<?php

namespace Database\Seeders;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('MovieSeeder: Skipping test data in production/staging environment');

            return;
        }

        // Use fixed slugs so MANUAL_TESTING_GUIDE and TC-MOVIE-* scenarios pass (the-matrix-1999, inception-2010).
        $matrix = Movie::updateOrCreate(
            ['slug' => 'the-matrix-1999'],
            [
                'title' => 'The Matrix',
                'release_year' => 1999,
                'director' => 'The Wachowskis',
            ]
        );

        if (! $matrix->default_description_id) {
            $desc = MovieDescription::create([
                'movie_id' => $matrix->id,
                'locale' => Locale::EN_US,
                'text' => 'A hacker discovers the truth about reality and leads a rebellion.',
                'context_tag' => ContextTag::MODERN,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock',
            ]);
            $matrix->update(['default_description_id' => $desc->id]);
        }

        \App\Models\TmdbSnapshot::updateOrCreate(
            ['entity_type' => 'MOVIE', 'entity_id' => $matrix->id],
            ['tmdb_id' => 603, 'tmdb_type' => 'movie', 'raw_data' => ['id' => 603, 'title' => 'The Matrix'], 'fetched_at' => \Illuminate\Support\Carbon::now()]
        );

        $inception = Movie::updateOrCreate(
            ['slug' => 'inception-2010'],
            [
                'title' => 'Inception',
                'release_year' => 2010,
                'director' => 'Christopher Nolan',
            ]
        );

        if (! $inception->default_description_id) {
            $desc2 = MovieDescription::create([
                'movie_id' => $inception->id,
                'locale' => Locale::EN_US,
                'text' => 'A thief enters dreams to steal secrets, facing a final, complex mission.',
                'context_tag' => ContextTag::MODERN,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock',
            ]);
            $inception->update(['default_description_id' => $desc2->id]);
        }

        \App\Models\TmdbSnapshot::updateOrCreate(
            ['entity_type' => 'MOVIE', 'entity_id' => $inception->id],
            ['tmdb_id' => 27205, 'tmdb_type' => 'movie', 'raw_data' => ['id' => 27205, 'title' => 'Inception', 'overview' => 'A thief stealing corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.', 'release_date' => '2010-07-15'], 'fetched_at' => \Illuminate\Support\Carbon::now()]
        );

        $attach = function (Movie $movie, array $names) {
            $ids = [];
            foreach ($names as $name) {
                $genre = Genre::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name]);
                $ids[] = $genre->id;
            }
            $movie->genres()->syncWithoutDetaching($ids);
        };

        $attach($matrix, ['Action', 'Sci-Fi']);
        $attach($inception, ['Action', 'Sci-Fi', 'Thriller']);

        $movies = [
            ['Interstellar', 2014, 'Christopher Nolan', 'Sci-Fi', 'Astronauts travel through a wormhole in space in an attempt to ensure humanity survival.', 157336],
            ['Pulp Fiction', 1994, 'Quentin Tarantino', 'Crime', 'The lives of two mob hitmen, a boxer, a gangster and his wife intertwine.', 680],
            ['The Shawshank Redemption', 1994, 'Frank Darabont', 'Drama', 'Two imprisoned men bond over a number of years, finding solace and eventual redemption through acts of common decency.', 278],
            ['The Dark Knight', 2008, 'Christopher Nolan', 'Action', 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham.', 155],
            ['Fight Club', 1999, 'David Fincher', 'Drama', 'An insomniac office worker and a devil-may-care soap maker form an underground fight club.', 550],
            ['Forrest Gump', 1994, 'Robert Zemeckis', 'Drama', 'The presidencies of Kennedy and Johnson, the Vietnam War, and other historical events unfold through the perspective of an Alabama man.', 13],
            ['Gladiator', 2000, 'Ridley Scott', 'Action', 'A former Roman General sets out to exact vengeance against the corrupt emperor who murdered his family and sent him into slavery.', 98],
            ['The Godfather', 1972, 'Francis Ford Coppola', 'Crime', 'The aging patriarch of an organized crime dynasty transfers control of his clandestine empire to his reluctant son.', 238],
            ['Schindler\'s List', 1993, 'Steven Spielberg', 'History', 'In German-occupied Poland during World War II, industrialist Oskar Schindler gradually becomes concerned for his Jewish workforce.', 424],
            ['The Lord of the Rings: The Fellowship of the Ring', 2001, 'Peter Jackson', 'Adventure', 'A meek Hobbit from the Shire and eight companions set out on a journey to destroy the powerful One Ring.', 120],
            ['Goodfellas', 1990, 'Martin Scorsese', 'Crime', 'The story of Henry Hill and his life in the mob, covering his relationship with his wife Karen Hill.', 769],
            ['Se7en', 1995, 'David Fincher', 'Crime', 'Two detectives, a rookie and a veteran, hunt a serial killer who uses the seven deadly sins as his motives.', 807],
            ['The Silence of the Lambs', 1991, 'Jonathan Demme', 'Thriller', 'A young FBI cadet must receive the help of an incarcerated and manipulative cannibal killer.', 274],
            ['Star Wars', 1977, 'George Lucas', 'Adventure', 'Luke Skywalker joins forces with a Jedi Knight, a cocky pilot, a Wookiee and two droids to save the galaxy.', 11],
            ['Saving Private Ryan', 1998, 'Steven Spielberg', 'War', 'Following the Normandy Landings, a group of U.S. soldiers go behind enemy lines to retrieve a paratrooper.', 857],
            ['The Green Mile', 1999, 'Frank Darabont', 'Drama', 'The lives of guards on Death Row are affected by one of their charges: a black man accused of child murder.', 491],
            ['Parasite', 2019, 'Bong Joon-ho', 'Thriller', 'Greed and class discrimination threaten the newly formed symbiotic relationship between the wealthy Park family and the destitute Kim clan.', 496243],
            ['Joker', 2019, 'Todd Phillips', 'Crime', 'In Gotham City, mentally troubled comedian Arthur Fleck is disregarded and mistreated by society.', 475557],
            ['Blade Runner 2049', 2017, 'Denis Villeneuve', 'Sci-Fi', 'A young Blade Runner\'s discovery of a long-buried secret leads him to track down former Blade Runner Rick Deckard.', 335984],
            ['Django Unchained', 2012, 'Quentin Tarantino', 'Western', 'With the help of a German bounty hunter, a freed slave sets out to rescue his wife from a brutal Mississippi plantation owner.', 68718],
            ['The Prestige', 2006, 'Christopher Nolan', 'Drama', 'After a tragic accident, two stage magicians engage in a battle to create the ultimate illusion.', 1124],
            ['Whiplash', 2014, 'Damien Chazelle', 'Music', 'A promising young drummer enrolls at a cut-throat music conservatory where his dreams of greatness are mentored by an instructor.', 244786],
            ['The Lion King', 1994, 'Roger Allers', 'Animation', 'A lion prince and his father are targeted by his bitter uncle, who wants to ascend the throne himself.', 8587],
        ];

        foreach ($movies as [$title, $year, $director, $genre, $description, $tmdbId]) {
            $movie = Movie::updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($title.'-'.$year)],
                [
                    'title' => $title,
                    'release_year' => $year,
                    'director' => $director,
                ]
            );

            if (! $movie->default_description_id) {
                $desc = MovieDescription::create([
                    'movie_id' => $movie->id,
                    'locale' => Locale::EN_US,
                    'text' => $description,
                    'context_tag' => ContextTag::MODERN,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => 'mock',
                ]);
                $movie->update(['default_description_id' => $desc->id]);
            }

            \App\Models\TmdbSnapshot::updateOrCreate(
                ['entity_type' => 'MOVIE', 'entity_id' => $movie->id],
                [
                    'tmdb_id' => $tmdbId,
                    'tmdb_type' => 'movie',
                    'raw_data' => ['id' => $tmdbId, 'title' => $title, 'overview' => $description],
                    'fetched_at' => \Illuminate\Support\Carbon::now(),
                ]
            );

            $attach($movie, [$genre]);
        }
    }
}
