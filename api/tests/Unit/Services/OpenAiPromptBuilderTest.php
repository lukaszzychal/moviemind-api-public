<?php

namespace Tests\Unit\Services;

use App\Services\OpenAiPromptBuilder;
use App\Services\PromptSanitizer;
use PHPUnit\Framework\TestCase;

class OpenAiPromptBuilderTest extends TestCase
{
    private OpenAiPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new OpenAiPromptBuilder(new PromptSanitizer);
    }

    public function test_tv_series_schema_has_no_error_property(): void
    {
        $prompts = $this->builder->buildTvSeriesPrompts('the-tom-and-jerry-show-2014', null, 'pl-PL', 'critical');

        $this->assertArrayNotHasKey('error', $prompts['schema']['schema']['properties']);
        $this->assertEquals(['title', 'first_air_year', 'description', 'genres'], $prompts['schema']['schema']['required']);
    }

    public function test_tv_show_schema_has_no_error_property(): void
    {
        $prompts = $this->builder->buildTvShowPrompts('the-tonight-show-2014', null, 'pl-PL', 'humorous');

        $this->assertArrayNotHasKey('error', $prompts['schema']['schema']['properties']);
        $this->assertEquals(['title', 'first_air_year', 'description', 'genres'], $prompts['schema']['schema']['required']);
    }

    public function test_movie_schema_has_no_error_property(): void
    {
        $prompts = $this->builder->buildMoviePrompts('inception-2010', null, 'en-US', 'modern');

        $this->assertArrayNotHasKey('error', $prompts['schema']['schema']['properties']);
        $this->assertEquals(['title', 'release_year', 'director', 'description', 'genres'], $prompts['schema']['schema']['required']);
    }

    public function test_person_schema_has_no_error_property_and_has_required_fields(): void
    {
        $prompts = $this->builder->buildPersonPrompts('christopher-nolan', null, 'en-US', 'default');

        $this->assertArrayNotHasKey('error', $prompts['schema']['schema']['properties']);
        $this->assertEquals(['name', 'biography'], $prompts['schema']['schema']['required']);
    }

    public function test_critical_context_instructions_are_neutral_to_entity_type(): void
    {
        $prompts = $this->builder->buildTvSeriesPrompts('the-tom-and-jerry-show-2014', null, 'pl-PL', 'critical');

        $this->assertStringContainsString('critical, analytical tone', $prompts['system_prompt']);
        $this->assertStringNotContainsString("film's themes", $prompts['system_prompt']);
    }
}
