<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Movie;
use App\Models\MovieReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MovieReport>
 */
class MovieReportFactory extends Factory
{
    protected $model = MovieReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movie_id' => Movie::factory(),
            'description_id' => null,
            'type' => $this->faker->randomElement(ReportType::cases()),
            'message' => $this->faker->sentence(),
            'suggested_fix' => $this->faker->optional()->sentence(),
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
            'verified_by' => null,
            'verified_at' => null,
            'resolved_at' => null,
        ];
    }
}
