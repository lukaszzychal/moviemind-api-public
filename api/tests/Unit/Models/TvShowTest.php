<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Person;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_slug_with_year(): void
    {
        $slug = TvShow::generateSlug('The Tonight Show', 1954);

        $this->assertEquals('the-tonight-show-1954', $slug);
    }

    public function test_can_generate_slug_without_year(): void
    {
        $slug = TvShow::generateSlug('The Tonight Show');

        $this->assertEquals('the-tonight-show', $slug);
    }

    public function test_generate_slug_handles_duplicates(): void
    {
        TvShow::factory()->create([
            'title' => 'Survivor',
            'slug' => 'survivor-2000',
            'first_air_date' => '2000-05-31',
        ]);

        $slug = TvShow::generateSlug('Survivor', 2000);

        $this->assertEquals('survivor-2000-2', $slug);
    }

    public function test_generate_slug_excludes_current_id_when_updating(): void
    {
        $tvShow = TvShow::factory()->create([
            'title' => 'Survivor',
            'slug' => 'survivor-2000',
            'first_air_date' => '2000-05-31',
        ]);

        $slug = TvShow::generateSlug('Survivor', 2000, $tvShow->id);

        $this->assertEquals('survivor-2000', $slug);
    }

    public function test_can_parse_slug_with_year(): void
    {
        $parsed = TvShow::parseSlug('the-tonight-show-1954');

        $this->assertEquals('the tonight show', $parsed['title']);
        $this->assertEquals(1954, $parsed['year']);
        $this->assertNull($parsed['suffix']);
    }

    public function test_can_parse_slug_with_year_and_suffix(): void
    {
        $parsed = TvShow::parseSlug('survivor-2000-2');

        $this->assertEquals('survivor', $parsed['title']);
        $this->assertEquals(2000, $parsed['year']);
        $this->assertEquals('2', $parsed['suffix']);
    }

    public function test_can_parse_slug_without_year(): void
    {
        $parsed = TvShow::parseSlug('the-tonight-show');

        $this->assertEquals('the tonight show', $parsed['title']);
        $this->assertNull($parsed['year']);
        $this->assertNull($parsed['suffix']);
    }

    public function test_has_many_descriptions(): void
    {
        $tvShow = TvShow::factory()->create();
        TvShowDescription::factory()->create([
            'tv_show_id' => $tvShow->id,
            'locale' => 'en-US',
            'context_tag' => 'modern',
        ]);
        TvShowDescription::factory()->create([
            'tv_show_id' => $tvShow->id,
            'locale' => 'en-US',
            'context_tag' => 'critical',
        ]);
        TvShowDescription::factory()->create([
            'tv_show_id' => $tvShow->id,
            'locale' => 'pl-PL',
            'context_tag' => 'modern',
        ]);

        $this->assertCount(3, $tvShow->descriptions);
    }

    public function test_has_one_default_description(): void
    {
        $tvShow = TvShow::factory()->create();
        $description = TvShowDescription::factory()->create(['tv_show_id' => $tvShow->id]);
        $tvShow->update(['default_description_id' => $description->id]);

        $this->assertNotNull($tvShow->defaultDescription);
        $this->assertEquals($description->id, $tvShow->defaultDescription->id);
    }

    public function test_belongs_to_many_people(): void
    {
        $tvShow = TvShow::factory()->create();
        $person1 = Person::create([
            'name' => 'Jimmy Fallon',
            'slug' => 'jimmy-fallon-1974',
        ]);
        $person2 = Person::create([
            'name' => 'Lorne Michaels',
            'slug' => 'lorne-michaels-1944',
        ]);

        $tvShow->people()->attach($person1->id, ['role' => 'HOST', 'character_name' => null]);
        $tvShow->people()->attach($person2->id, ['role' => 'PRODUCER']);

        $this->assertCount(2, $tvShow->people);
        $this->assertEquals('HOST', $tvShow->people->first()->pivot->role);
    }
}
