<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TvSeriesDescription>
 */
class TvSeriesDescriptionFactory extends Factory
{
    protected $model = TvSeriesDescription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tv_series_id' => TvSeries::factory(),
            'locale' => $this->faker->randomElement(Locale::cases()),
            'text' => $this->faker->paragraphs(3, true),
            'context_tag' => $this->faker->randomElement([null, ...ContextTag::cases()]),
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 1,
            'archived_at' => null,
        ];
    }
}
