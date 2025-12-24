<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TvShow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TvShow>
 */
class TvShowFactory extends Factory
{
    protected $model = TvShow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->words(3, true);
        $firstAirYear = $this->faker->year();
        $firstAirDate = $this->faker->dateTimeBetween("{$firstAirYear}-01-01", "{$firstAirYear}-12-31");

        return [
            'title' => ucwords($title),
            'slug' => \Illuminate\Support\Str::slug($title).'-'.$firstAirYear,
            'first_air_date' => $firstAirDate,
            'last_air_date' => $this->faker->optional()->dateTimeBetween($firstAirDate, 'now'),
            'number_of_seasons' => $this->faker->optional()->numberBetween(1, 20),
            'number_of_episodes' => $this->faker->optional()->numberBetween(10, 1000),
            'genres' => $this->faker->randomElements(['Talk', 'Reality', 'News', 'Documentary', 'Variety', 'Game Show'], 2),
            'show_type' => $this->faker->randomElement(['TALK_SHOW', 'REALITY', 'NEWS', 'DOCUMENTARY', 'VARIETY', 'GAME_SHOW']),
        ];
    }
}
