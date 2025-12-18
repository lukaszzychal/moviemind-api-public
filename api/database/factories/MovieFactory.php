<?php

namespace Database\Factories;

use App\Models\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movie>
 */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->words(3, true);
        $releaseYear = $this->faker->year();

        return [
            'title' => ucwords($title),
            'slug' => \Illuminate\Support\Str::slug($title).'-'.$releaseYear,
            'release_year' => $releaseYear,
            'director' => $this->faker->name(),
            'genres' => $this->faker->randomElements(['Action', 'Drama', 'Comedy', 'Thriller', 'Sci-Fi'], 2),
        ];
    }
}
