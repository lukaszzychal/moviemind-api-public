<?php

namespace Database\Factories;

use App\Models\TmdbSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TmdbSnapshot>
 */
class TmdbSnapshotFactory extends Factory
{
    protected $model = TmdbSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'MOVIE',
            'entity_id' => 1,
            'tmdb_id' => $this->faker->numberBetween(1, 1000000),
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => $this->faker->numberBetween(1, 1000000),
                'title' => $this->faker->sentence(3),
                'overview' => $this->faker->paragraph(),
                'release_date' => $this->faker->date('Y-m-d'),
            ],
            'fetched_at' => now(),
        ];
    }
}
