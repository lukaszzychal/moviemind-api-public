<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Events\TvSeriesGenerationRequested;
use App\Events\TvShowGenerationRequested;
use App\Models\Movie;
use App\Models\Person;
use App\Models\TvSeries;
use App\Models\TvShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GenerateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_generate_movie_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_movie_allowed_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'the-matrix',
                'locale' => 'en-US',
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix'
                && $event->locale === 'en-US';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
    }

    public function test_generate_movie_respects_locale_and_context(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix',
            'locale' => 'pl-PL',
            'context_tag' => 'modern',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'the-matrix',
                'locale' => 'pl-PL',
                'context_tag' => 'modern',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix'
                && $event->locale === 'pl-PL'
                && $event->contextTag === 'modern';
        });
    }

    public function test_generate_movie_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_description_generation');
        $movie = Movie::firstOrFail();

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $movie->slug,
                'existing_id' => $movie->id,
                'description_id' => $movie->default_description_id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) use ($movie) {
            return $event->slug === $movie->slug
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_person_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_bio_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'keanu-reeves',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_person_allowed_when_flag_on(): void
    {
        Feature::activate('ai_bio_generation');

        // Use a slug that doesn't exist in seeders
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'new-person-slug',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'new-person-slug',
                'locale' => 'en-US',
            ]);

        // Verify Event was dispatched
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-slug'
                && $event->locale === 'en-US';
        });

        // Note: Queue::fake() prevents Listener from executing, so we only check Event
        // If Event is dispatched and Listener is registered, Job will be queued
    }

    public function test_generate_person_respects_locale_and_context(): void
    {
        Feature::activate('ai_bio_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'new-person-slug',
            'locale' => 'pl-PL',
            'context_tag' => 'critical',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'new-person-slug',
                'locale' => 'pl-PL',
                'context_tag' => 'critical',
            ]);

        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'new-person-slug'
                && $event->locale === 'pl-PL'
                && $event->contextTag === 'critical';
        });
    }

    public function test_generate_person_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_bio_generation');
        $person = Person::firstOrFail();

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => $person->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $person->slug,
                'existing_id' => $person->id,
                'bio_id' => $person->default_bio_id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(PersonGenerationRequested::class, function ($event) use ($person) {
            return $event->slug === $person->slug
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_rejects_actor_entity_type(): void
    {
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'ACTOR',
            'entity_id' => 'test-actor',
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['entity_type']);
    }

    public function test_generate_requires_string_entity_id(): void
    {
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 123,
        ]);

        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['entity_id']);
    }

    public function test_generate_movie_with_default_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'default-context-movie',
            // No context_tag provided - should use default
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'locale',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'default-context-movie'
                && $event->locale === 'en-US'
                && ($event->contextTag === null || $event->contextTag === 'DEFAULT');
        });
    }

    public function test_generate_movie_with_humorous_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'humorous-context-movie',
            'context_tag' => 'humorous',
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'humorous-context-movie',
                'locale' => 'en-US',
                'context_tag' => 'humorous',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'humorous-context-movie'
                && $event->locale === 'en-US'
                && $event->contextTag === 'humorous';
        });
    }

    public function test_generate_movie_context_tag_null(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'null-context-movie',
            'context_tag' => null,
        ]);

        $resp->assertStatus(202);

        // When context_tag is explicitly null, should fallback to default
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'null-context-movie';
        });
    }

    public function test_generate_movie_with_invalid_context_tag(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'invalid-context-movie',
            'context_tag' => 'invalid-tag',
        ]);

        // Invalid context_tag should be rejected by validation
        // When single string is converted to array, validation error key is context_tag.0
        $resp->assertStatus(422)
            ->assertJsonValidationErrors(['context_tag.0']);
    }

    public function test_generate_movie_with_multiple_context_tags(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'multiple-context-movie',
            'context_tag' => ['modern', 'critical', 'humorous'],
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_ids',
                'status',
                'message',
                'slug',
                'context_tags',
                'locale',
                'jobs',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'multiple-context-movie',
                'context_tags' => ['modern', 'critical', 'humorous'],
                'locale' => 'en-US',
            ]);

        // Verify multiple events were dispatched (one for each context tag)
        Event::assertDispatched(MovieGenerationRequested::class, 3);
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'multiple-context-movie'
                && $event->contextTag === 'modern';
        });
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'multiple-context-movie'
                && $event->contextTag === 'critical';
        });
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'multiple-context-movie'
                && $event->contextTag === 'humorous';
        });
    }

    public function test_generate_person_with_multiple_context_tags(): void
    {
        Feature::activate('ai_bio_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'PERSON',
            'entity_id' => 'multiple-context-person',
            'context_tag' => ['modern', 'critical'],
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_ids',
                'status',
                'message',
                'slug',
                'context_tags',
                'locale',
                'jobs',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'multiple-context-person',
                'context_tags' => ['modern', 'critical'],
                'locale' => 'en-US',
            ]);

        // Verify multiple events were dispatched (one for each context tag)
        Event::assertDispatched(PersonGenerationRequested::class, 2);
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'multiple-context-person'
                && $event->contextTag === 'modern';
        });
        Event::assertDispatched(PersonGenerationRequested::class, function ($event) {
            return $event->slug === 'multiple-context-person'
                && $event->contextTag === 'critical';
        });
    }

    public function test_generate_movie_with_single_context_tag_array_backward_compatibility(): void
    {
        Feature::activate('ai_description_generation');

        // Single context tag as array should work (backward compatibility)
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'single-array-context-movie',
            'context_tag' => ['modern'],
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'single-array-context-movie',
                'context_tag' => 'modern',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'single-array-context-movie'
                && $event->contextTag === 'modern';
        });
    }

    public function test_generate_movie_with_empty_context_tag_array(): void
    {
        Feature::activate('ai_description_generation');

        // Empty array should be treated as null
        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => 'empty-context-movie',
            'context_tag' => [],
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'locale',
            ]);

        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'empty-context-movie'
                && ($event->contextTag === null || $event->contextTag === 'DEFAULT');
        });
    }

    public function test_generate_tv_series_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SERIES',
            'entity_id' => 'breaking-bad-2008',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_tv_series_allowed_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SERIES',
            'entity_id' => 'breaking-bad-2008',
        ]);

        if ($resp->status() !== 202) {
            dump($resp->json());
        }

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'breaking-bad-2008',
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(TvSeriesGenerationRequested::class, function ($event) {
            return $event->slug === 'breaking-bad-2008'
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_tv_series_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_description_generation');

        $tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => 'breaking-bad-2008',
        ]);

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SERIES',
            'entity_id' => $tvSeries->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $tvSeries->slug,
                'existing_id' => $tvSeries->id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(TvSeriesGenerationRequested::class, function ($event) use ($tvSeries) {
            return $event->slug === $tvSeries->slug
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_tv_show_blocked_when_flag_off(): void
    {
        Feature::deactivate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SHOW',
            'entity_id' => 'the-tonight-show-1954',
        ]);

        $resp->assertStatus(403)
            ->assertJson(['error' => 'Feature not available']);
    }

    public function test_generate_tv_show_allowed_when_flag_on(): void
    {
        Feature::activate('ai_description_generation');

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SHOW',
            'entity_id' => 'the-tonight-show-1954',
        ]);

        $resp->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ])
            ->assertJson([
                'status' => 'PENDING',
                'slug' => 'the-tonight-show-1954',
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(TvShowGenerationRequested::class, function ($event) {
            return $event->slug === 'the-tonight-show-1954'
                && $event->locale === 'en-US';
        });
    }

    public function test_generate_tv_show_existing_slug_triggers_regeneration_flow(): void
    {
        Feature::activate('ai_description_generation');

        $tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => 'the-tonight-show-1954',
        ]);

        $resp = $this->postJson('/api/v1/generate', [
            'entity_type' => 'TV_SHOW',
            'entity_id' => $tvShow->slug,
        ]);

        $resp->assertStatus(202)
            ->assertJson([
                'status' => 'PENDING',
                'slug' => $tvShow->slug,
                'existing_id' => $tvShow->id,
                'locale' => 'en-US',
            ]);

        Event::assertDispatched(TvShowGenerationRequested::class, function ($event) use ($tvShow) {
            return $event->slug === $tvShow->slug
                && $event->locale === 'en-US';
        });
    }
}
