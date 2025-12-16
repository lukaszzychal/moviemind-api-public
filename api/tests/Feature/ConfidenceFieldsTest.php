<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ConfidenceFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['services.tmdb.api_key' => 'test-api-key']);
    }

    public function test_movie_show_returns_confidence_fields_when_queuing_generation(): void
    {
        Feature::activate('ai_description_generation');

        // Use fake EntityVerificationService with unique slug
        $fake = $this->fakeEntityVerificationService();
        $slug = 'unique-test-movie-'.uniqid();
        $fake->setMovie($slug, [
            'title' => 'Unique Test Movie',
            'release_date' => '2024-01-01',
            'overview' => 'A test movie',
            'id' => 999999,
            'director' => 'Test Director',
        ]);

        $res = $this->getJson("/api/v1/movies/{$slug}");
        $res->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'confidence',
                'confidence_level',
                'locale',
            ]);

        // Verify confidence is a number (float)
        $confidence = $res->json('confidence');
        $this->assertIsFloat($confidence);
        $this->assertGreaterThanOrEqual(0.0, $confidence);
        $this->assertLessThanOrEqual(1.0, $confidence);

        // Verify confidence_level is a valid string
        $confidenceLevel = $res->json('confidence_level');
        $this->assertIsString($confidenceLevel);
        $this->assertContains($confidenceLevel, ['high', 'medium', 'low', 'very_low', 'unknown']);

        // For a well-formed slug, confidence should be reasonable
        $this->assertGreaterThanOrEqual(0.3, $confidence);
    }

    public function test_person_show_returns_confidence_fields_when_queuing_generation(): void
    {
        Feature::activate('ai_bio_generation');

        // Use fake EntityVerificationService with unique slug
        $fake = $this->fakeEntityVerificationService();
        $slug = 'unique-test-person-'.uniqid();
        $fake->setPerson($slug, [
            'name' => 'Unique Test Person',
            'birthday' => '1980-01-01',
            'place_of_birth' => 'Test City',
            'id' => 999999,
            'biography' => 'An actor',
        ]);

        $res = $this->getJson("/api/v1/people/{$slug}");
        $res->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'confidence',
                'confidence_level',
                'locale',
            ]);

        // Verify confidence is a number (float)
        $confidence = $res->json('confidence');
        $this->assertIsFloat($confidence);
        $this->assertGreaterThanOrEqual(0.0, $confidence);
        $this->assertLessThanOrEqual(1.0, $confidence);

        // Verify confidence_level is a valid string
        $confidenceLevel = $res->json('confidence_level');
        $this->assertIsString($confidenceLevel);
        $this->assertContains($confidenceLevel, ['high', 'medium', 'low', 'very_low', 'unknown']);

        // For a well-formed slug, confidence should be reasonable
        $this->assertGreaterThanOrEqual(0.3, $confidence);
    }

    public function test_movie_show_confidence_matches_slug_validation(): void
    {
        Feature::activate('ai_description_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('high-confidence-slug-2024', null);

        $res = $this->getJson('/api/v1/movies/high-confidence-slug-2024');
        $res->assertStatus(202);

        $confidence = $res->json('confidence');
        $confidenceLevel = $res->json('confidence_level');

        // High confidence slug should have high confidence
        $this->assertGreaterThanOrEqual(0.7, $confidence);
        $this->assertContains($confidenceLevel, ['high', 'medium']);
    }

    public function test_person_show_confidence_matches_slug_validation(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('high-confidence-person-1980', null);

        $res = $this->getJson('/api/v1/people/high-confidence-person-1980');
        $res->assertStatus(202);

        $confidence = $res->json('confidence');
        $confidenceLevel = $res->json('confidence_level');

        // High confidence slug should have high confidence
        $this->assertGreaterThanOrEqual(0.7, $confidence);
        $this->assertContains($confidenceLevel, ['high', 'medium']);
    }

    public function test_movie_show_confidence_is_not_null_or_unknown(): void
    {
        Feature::activate('ai_description_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService - set movie to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setMovie('test-movie-slug', null);

        $res = $this->getJson('/api/v1/movies/test-movie-slug');
        $res->assertStatus(202);

        // Confidence should never be null
        $this->assertNotNull($res->json('confidence'));

        // Confidence level should never be "unknown" for valid slugs
        $this->assertNotSame('unknown', $res->json('confidence_level'));
    }

    public function test_person_show_confidence_is_not_null_or_unknown(): void
    {
        Feature::activate('ai_bio_generation');
        Feature::deactivate('tmdb_verification');

        // Use fake EntityVerificationService - set person to null (not found)
        $fake = $this->fakeEntityVerificationService();
        $fake->setPerson('test-person-slug', null);

        $res = $this->getJson('/api/v1/people/test-person-slug');
        $res->assertStatus(202);

        // Confidence should never be null
        $this->assertNotNull($res->json('confidence'));

        // Confidence level should never be "unknown" for valid slugs
        $this->assertNotSame('unknown', $res->json('confidence_level'));
    }
}
