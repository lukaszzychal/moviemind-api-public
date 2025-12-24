<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Person;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvSeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_slug_with_year(): void
    {
        $slug = TvSeries::generateSlug('Breaking Bad', 2008);

        $this->assertEquals('breaking-bad-2008', $slug);
    }

    public function test_can_generate_slug_without_year(): void
    {
        $slug = TvSeries::generateSlug('Breaking Bad');

        $this->assertEquals('breaking-bad', $slug);
    }

    public function test_generate_slug_handles_duplicates(): void
    {
        TvSeries::factory()->create([
            'title' => 'The Office',
            'slug' => 'the-office-2005',
            'first_air_date' => '2005-03-24',
        ]);

        $slug = TvSeries::generateSlug('The Office', 2005);

        $this->assertEquals('the-office-2005-2', $slug);
    }

    public function test_generate_slug_excludes_current_id_when_updating(): void
    {
        $tvSeries = TvSeries::factory()->create([
            'title' => 'The Office',
            'slug' => 'the-office-2005',
            'first_air_date' => '2005-03-24',
        ]);

        $slug = TvSeries::generateSlug('The Office', 2005, $tvSeries->id);

        $this->assertEquals('the-office-2005', $slug);
    }

    public function test_can_parse_slug_with_year(): void
    {
        $parsed = TvSeries::parseSlug('breaking-bad-2008');

        $this->assertEquals('breaking bad', $parsed['title']);
        $this->assertEquals(2008, $parsed['year']);
        $this->assertNull($parsed['suffix']);
    }

    public function test_can_parse_slug_with_year_and_suffix(): void
    {
        $parsed = TvSeries::parseSlug('the-office-2005-2');

        $this->assertEquals('the office', $parsed['title']);
        $this->assertEquals(2005, $parsed['year']);
        $this->assertEquals('2', $parsed['suffix']);
    }

    public function test_can_parse_slug_without_year(): void
    {
        $parsed = TvSeries::parseSlug('breaking-bad');

        $this->assertEquals('breaking bad', $parsed['title']);
        $this->assertNull($parsed['year']);
        $this->assertNull($parsed['suffix']);
    }

    public function test_has_many_descriptions(): void
    {
        $tvSeries = TvSeries::factory()->create();
        TvSeriesDescription::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'locale' => 'en-US',
            'context_tag' => 'modern',
        ]);
        TvSeriesDescription::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'locale' => 'en-US',
            'context_tag' => 'critical',
        ]);
        TvSeriesDescription::factory()->create([
            'tv_series_id' => $tvSeries->id,
            'locale' => 'pl-PL',
            'context_tag' => 'modern',
        ]);

        $this->assertCount(3, $tvSeries->descriptions);
    }

    public function test_has_one_default_description(): void
    {
        $tvSeries = TvSeries::factory()->create();
        $description = TvSeriesDescription::factory()->create(['tv_series_id' => $tvSeries->id]);
        $tvSeries->update(['default_description_id' => $description->id]);

        $this->assertNotNull($tvSeries->defaultDescription);
        $this->assertEquals($description->id, $tvSeries->defaultDescription->id);
    }

    public function test_belongs_to_many_people(): void
    {
        $tvSeries = TvSeries::factory()->create();
        $person1 = Person::create([
            'name' => 'Bryan Cranston',
            'slug' => 'bryan-cranston-1956',
        ]);
        $person2 = Person::create([
            'name' => 'Vince Gilligan',
            'slug' => 'vince-gilligan-1967',
        ]);

        $tvSeries->people()->attach($person1->id, ['role' => 'ACTOR', 'character_name' => 'Walter White']);
        $tvSeries->people()->attach($person2->id, ['role' => 'CREATOR']);

        $this->assertCount(2, $tvSeries->people);
        $this->assertEquals('ACTOR', $tvSeries->people->first()->pivot->role);
    }
}
