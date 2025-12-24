<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TvSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TvSeries>
 */
class TvSeriesFactory extends Factory
{
    protected $model = TvSeries::class;

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
            'number_of_seasons' => $this->faker->numberBetween(1, 10),
            'number_of_episodes' => $this->faker->numberBetween(10, 200),
            'genres' => $this->faker->randomElements(['Drama', 'Comedy', 'Sci-Fi', 'Crime', 'Thriller'], 2),
        ];
    }
}
