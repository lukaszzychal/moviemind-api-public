<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MissingEntityGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_movie_missing_returns_202_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');
        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'slug'])
            ->assertJson(['locale' => 'en-US']);
    }

    public function test_movie_missing_returns_404_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');
        $res = $this->getJson('/api/v1/movies/annihilation');
        $res->assertStatus(404);
    }

    public function test_person_missing_returns_202_when_flag_on(): void
    {
        Feature::activate('ai_bio_generation');
        $res = $this->getJson('/api/v1/people/john-doe');
        $res->assertStatus(202)->assertJsonStructure(['job_id', 'status', 'slug'])
            ->assertJson(['locale' => 'en-US']);
    }

    public function test_movie_missing_reuses_active_job(): void
    {
        Feature::activate('ai_description_generation');
        $first = $this->getJson('/api/v1/movies/brand-new-movie');
        $first->assertStatus(202);
        $jobId = $first->json('job_id');

        $second = $this->getJson('/api/v1/movies/brand-new-movie');
        $second->assertStatus(202);
        $this->assertSame($jobId, $second->json('job_id'));
    }

    public function test_person_missing_returns_404_when_flag_off(): void
    {
        Feature::deactivate('ai_bio_generation');
        $res = $this->getJson('/api/v1/people/john-doe');
        $res->assertStatus(404);
    }
}
