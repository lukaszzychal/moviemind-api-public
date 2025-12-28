<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\TvSeries;
use App\Models\TvSeriesReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TvSeriesReport>
 */
class TvSeriesReportFactory extends Factory
{
    protected $model = TvSeriesReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tv_series_id' => TvSeries::factory(),
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
