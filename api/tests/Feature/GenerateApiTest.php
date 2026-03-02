<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Events\TvSeriesGenerationRequested;
use App\Events\TvShowGenerationRequested;
use App\Models\Movie;
use App\Models\Person;
use App\Models\SubscriptionPlan;
use App\Models\TvSeries;
use App\Models\TvShow;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GenerateApiTest extends TestCase
{
    use RefreshDatabase;

    private $response = null;

    private $movie = null;

    private $person = null;

    private $tvSeries = null;

    private $tvShow = null;

    private string $apiKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Queue::fake();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        $plan = SubscriptionPlan::where('name', 'pro')->first() ?? SubscriptionPlan::where('name', 'free')->first();
        if (! $plan) {
            $plan = SubscriptionPlan::factory()->create(['name' => 'pro', 'features' => ['read', 'generate']]);
        }
        $result = app(ApiKeyService::class)->createKey('GenerateApiTest', $plan->id);
        $this->apiKey = $result['key'];
    }

    public function test_generate_movie_blocked_when_flag_off(): void
    {
        $this->givenFeatureFlagDisabled('ai_description_generation')
            ->whenGeneratingMovie('the-matrix')
            ->thenShouldReturn403WithError('Feature not available');
    }

    public function test_generate_movie_allowed_when_flag_on(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('the-matrix')
            ->thenShouldReturn202WithJobStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('the-matrix')
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'the-matrix'
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_movie_respects_locale_and_context(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('the-matrix', ['locale' => 'pl-PL', 'context_tag' => 'modern'])
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('the-matrix')
            ->andShouldHaveLocale('pl-PL')
            ->andShouldHaveContextTag('modern')
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'the-matrix'
                    && $event->locale === 'pl-PL'
                    && $event->contextTag === 'modern';
            });
    }

    public function test_generate_movie_existing_slug_triggers_regeneration_flow(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->andMovieExistsInDatabase()
            ->whenGeneratingMovie($this->movie->slug)
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug($this->movie->slug)
            ->andShouldHaveExistingId($this->movie->id)
            ->andShouldHaveDescriptionId($this->movie->default_description_id)
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === $this->movie->slug
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_person_blocked_when_flag_off(): void
    {
        $this->givenFeatureFlagDisabled('ai_bio_generation')
            ->whenGeneratingPerson('keanu-reeves')
            ->thenShouldReturn403WithError('Feature not available');
    }

    public function test_generate_person_allowed_when_flag_on(): void
    {
        $this->givenFeatureFlagEnabled('ai_bio_generation')
            ->whenGeneratingPerson('new-person-slug')
            ->thenShouldReturn202WithJobStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('new-person-slug')
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(PersonGenerationRequested::class, function ($event) {
                return $event->slug === 'new-person-slug'
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_person_respects_locale_and_context(): void
    {
        $this->givenFeatureFlagEnabled('ai_bio_generation')
            ->whenGeneratingPerson('new-person-slug', ['locale' => 'pl-PL', 'context_tag' => 'critical'])
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('new-person-slug')
            ->andShouldHaveLocale('pl-PL')
            ->andShouldHaveContextTag('critical')
            ->andEventShouldBeDispatched(PersonGenerationRequested::class, function ($event) {
                return $event->slug === 'new-person-slug'
                    && $event->locale === 'pl-PL'
                    && $event->contextTag === 'critical';
            });
    }

    public function test_generate_person_existing_slug_triggers_regeneration_flow(): void
    {
        $this->givenFeatureFlagEnabled('ai_bio_generation')
            ->andPersonExistsInDatabase()
            ->whenGeneratingPerson($this->person->slug)
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug($this->person->slug)
            ->andShouldHaveExistingId($this->person->id)
            ->andShouldHaveBioId($this->person->default_bio_id)
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(PersonGenerationRequested::class, function ($event) {
                return $event->slug === $this->person->slug
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_rejects_actor_entity_type(): void
    {
        $this->whenGeneratingWithEntityType('ACTOR', 'test-actor')
            ->thenShouldReturn422()
            ->andShouldHaveValidationError('entity_type');
    }

    public function test_generate_requires_string_entity_id(): void
    {
        $this->whenGeneratingMovieWithInvalidEntityId(123)
            ->thenShouldReturn422()
            ->andShouldHaveValidationError('entity_id');
    }

    public function test_generate_movie_with_default_context_tag(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('default-context-movie')
            ->thenShouldReturn202()
            ->andShouldHaveJobStructure()
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'default-context-movie'
                    && $event->locale === 'en-US'
                    && ($event->contextTag === null || $event->contextTag === 'DEFAULT');
            });
    }

    public function test_generate_movie_with_humorous_context_tag(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('humorous-context-movie', ['context_tag' => 'humorous'])
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('humorous-context-movie')
            ->andShouldHaveLocale('en-US')
            ->andShouldHaveContextTag('humorous')
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'humorous-context-movie'
                    && $event->locale === 'en-US'
                    && $event->contextTag === 'humorous';
            });
    }

    public function test_generate_movie_context_tag_null(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('null-context-movie', ['context_tag' => null])
            ->thenShouldReturn202()
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'null-context-movie';
            });
    }

    public function test_generate_movie_with_invalid_context_tag(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('invalid-context-movie', ['context_tag' => 'invalid-tag'])
            ->thenShouldReturn422()
            ->andShouldHaveValidationError('context_tag.0');
    }

    public function test_generate_movie_with_multiple_context_tags(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('multiple-context-movie', ['context_tag' => ['modern', 'critical', 'humorous']])
            ->thenShouldReturn202()
            ->andShouldHaveMultipleJobsStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('multiple-context-movie')
            ->andShouldHaveContextTags(['modern', 'critical', 'humorous'])
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatchedTimes(MovieGenerationRequested::class, 3)
            ->andEventShouldHaveContextTag('modern')
            ->andEventShouldHaveContextTag('critical')
            ->andEventShouldHaveContextTag('humorous');
    }

    public function test_generate_person_with_multiple_context_tags(): void
    {
        $this->givenFeatureFlagEnabled('ai_bio_generation')
            ->whenGeneratingPerson('multiple-context-person', ['context_tag' => ['modern', 'critical']])
            ->thenShouldReturn202()
            ->andShouldHaveMultipleJobsStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('multiple-context-person')
            ->andShouldHaveContextTags(['modern', 'critical'])
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatchedTimes(PersonGenerationRequested::class, 2)
            ->andEventShouldHaveContextTag('modern')
            ->andEventShouldHaveContextTag('critical');
    }

    public function test_generate_movie_with_single_context_tag_array_backward_compatibility(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('single-array-context-movie', ['context_tag' => ['modern']])
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('single-array-context-movie')
            ->andShouldHaveContextTag('modern')
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'single-array-context-movie'
                    && $event->contextTag === 'modern';
            });
    }

    public function test_generate_movie_with_empty_context_tag_array(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingMovie('empty-context-movie', ['context_tag' => []])
            ->thenShouldReturn202()
            ->andShouldHaveJobStructure()
            ->andEventShouldBeDispatched(MovieGenerationRequested::class, function ($event) {
                return $event->slug === 'empty-context-movie'
                    && ($event->contextTag === null || $event->contextTag === 'DEFAULT');
            });
    }

    public function test_generate_tv_series_blocked_when_flag_off(): void
    {
        $this->givenFeatureFlagDisabled('ai_description_generation')
            ->whenGeneratingTvSeries('breaking-bad-2008')
            ->thenShouldReturn403WithError('Feature not available');
    }

    public function test_generate_tv_series_allowed_when_flag_on(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingTvSeries('breaking-bad-2008')
            ->thenShouldReturn202WithJobStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('breaking-bad-2008')
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(TvSeriesGenerationRequested::class, function ($event) {
                return $event->slug === 'breaking-bad-2008'
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_tv_series_existing_slug_triggers_regeneration_flow(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->andTvSeriesExistsInDatabase('breaking-bad-2008')
            ->whenGeneratingTvSeries($this->tvSeries->slug)
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug($this->tvSeries->slug)
            ->andShouldHaveExistingId($this->tvSeries->id)
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(TvSeriesGenerationRequested::class, function ($event) {
                return $event->slug === $this->tvSeries->slug
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_tv_show_blocked_when_flag_off(): void
    {
        $this->givenFeatureFlagDisabled('ai_description_generation')
            ->whenGeneratingTvShow('the-tonight-show-1954')
            ->thenShouldReturn403WithError('Feature not available');
    }

    public function test_generate_tv_show_allowed_when_flag_on(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->whenGeneratingTvShow('the-tonight-show-1954')
            ->thenShouldReturn202WithJobStructure()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug('the-tonight-show-1954')
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(TvShowGenerationRequested::class, function ($event) {
                return $event->slug === 'the-tonight-show-1954'
                    && $event->locale === 'en-US';
            });
    }

    public function test_generate_tv_show_existing_slug_triggers_regeneration_flow(): void
    {
        $this->givenFeatureFlagEnabled('ai_description_generation')
            ->andTvShowExistsInDatabase('the-tonight-show-1954')
            ->whenGeneratingTvShow($this->tvShow->slug)
            ->thenShouldReturn202()
            ->andShouldHaveStatus('PENDING')
            ->andShouldHaveSlug($this->tvShow->slug)
            ->andShouldHaveExistingId($this->tvShow->id)
            ->andShouldHaveLocale('en-US')
            ->andEventShouldBeDispatched(TvShowGenerationRequested::class, function ($event) {
                return $event->slug === $this->tvShow->slug
                    && $event->locale === 'en-US';
            });
    }

    // ============================================
    // GIVEN helpers - Ustalenie kontekstu
    // ============================================

    private function givenFeatureFlagEnabled(string $feature): self
    {
        Feature::activate($feature);

        return $this;
    }

    private function givenFeatureFlagDisabled(string $feature): self
    {
        Feature::deactivate($feature);

        return $this;
    }

    private function andMovieExistsInDatabase(): self
    {
        $this->movie = Movie::firstOrFail();

        return $this;
    }

    private function andPersonExistsInDatabase(): self
    {
        $this->person = Person::firstOrFail();

        return $this;
    }

    private function andTvSeriesExistsInDatabase(string $slug): self
    {
        $this->tvSeries = TvSeries::factory()->create([
            'title' => 'Breaking Bad',
            'slug' => $slug,
        ]);

        return $this;
    }

    private function andTvShowExistsInDatabase(string $slug): self
    {
        $this->tvShow = TvShow::factory()->create([
            'title' => 'The Tonight Show',
            'slug' => $slug,
        ]);

        return $this;
    }

    // ============================================
    // WHEN helpers - Wykonanie akcji
    // ============================================

    private function whenGeneratingMovie(string $slug, array $options = []): self
    {
        $payload = array_merge([
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ], $options);

        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', $payload);

        return $this;
    }

    private function whenGeneratingPerson(string $slug, array $options = []): self
    {
        $payload = array_merge([
            'entity_type' => 'PERSON',
            'entity_id' => $slug,
        ], $options);

        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', $payload);

        return $this;
    }

    private function whenGeneratingTvSeries(string $slug, array $options = []): self
    {
        $payload = array_merge([
            'entity_type' => 'TV_SERIES',
            'entity_id' => $slug,
        ], $options);

        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', $payload);

        return $this;
    }

    private function whenGeneratingTvShow(string $slug, array $options = []): self
    {
        $payload = array_merge([
            'entity_type' => 'TV_SHOW',
            'entity_id' => $slug,
        ], $options);

        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', $payload);

        return $this;
    }

    private function whenGeneratingWithEntityType(string $entityType, string $entityId): self
    {
        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        return $this;
    }

    private function whenGeneratingMovieWithInvalidEntityId($entityId): self
    {
        $this->response = $this->withHeader('X-API-Key', $this->apiKey)->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $entityId,
        ]);

        return $this;
    }

    // ============================================
    // THEN helpers - Weryfikacja rezultatu
    // ============================================

    private function thenShouldReturn202(): self
    {
        $this->response->assertStatus(202);

        return $this;
    }

    private function thenShouldReturn403WithError(string $error): self
    {
        $this->response->assertStatus(403)
            ->assertJson(['error' => $error]);

        return $this;
    }

    private function thenShouldReturn422(): self
    {
        $this->response->assertStatus(422);

        return $this;
    }

    private function thenShouldReturn202WithJobStructure(): self
    {
        $this->response->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'message',
                'slug',
            ]);

        return $this;
    }

    // ============================================
    // AND helpers - Dodatkowe weryfikacje
    // ============================================

    private function andShouldHaveStatus(string $status): self
    {
        $this->response->assertJson(['status' => $status]);

        return $this;
    }

    private function andShouldHaveSlug(string $slug): self
    {
        $this->response->assertJson(['slug' => $slug]);

        return $this;
    }

    private function andShouldHaveLocale(string $locale): self
    {
        $this->response->assertJson(['locale' => $locale]);

        return $this;
    }

    private function andShouldHaveContextTag(string $contextTag): self
    {
        $this->response->assertJson(['context_tag' => $contextTag]);

        return $this;
    }

    private function andShouldHaveContextTags(array $contextTags): self
    {
        $this->response->assertJson(['context_tags' => $contextTags]);

        return $this;
    }

    private function andShouldHaveExistingId(string $id): self
    {
        $this->response->assertJson(['existing_id' => $id]);

        return $this;
    }

    private function andShouldHaveDescriptionId(?string $id): self
    {
        $this->response->assertJson(['description_id' => $id]);

        return $this;
    }

    private function andShouldHaveBioId(?string $id): self
    {
        $this->response->assertJson(['bio_id' => $id]);

        return $this;
    }

    private function andShouldHaveJobStructure(): self
    {
        $this->response->assertJsonStructure([
            'job_id',
            'status',
            'slug',
            'locale',
        ]);

        return $this;
    }

    private function andShouldHaveMultipleJobsStructure(): self
    {
        $this->response->assertJsonStructure([
            'job_ids',
            'status',
            'message',
            'slug',
            'context_tags',
            'locale',
            'jobs',
        ]);

        return $this;
    }

    private function andShouldHaveValidationError(string $field): self
    {
        $this->response->assertJsonValidationErrors([$field]);

        return $this;
    }

    private function andEventShouldBeDispatched(string $eventClass, callable $assertion): self
    {
        Event::assertDispatched($eventClass, $assertion);

        return $this;
    }

    private function andEventShouldBeDispatchedTimes(string $eventClass, int $times): self
    {
        Event::assertDispatched($eventClass, $times);

        return $this;
    }

    private function andEventShouldHaveContextTag(string $contextTag): self
    {
        // Check all possible generation event types
        $eventTypes = [
            MovieGenerationRequested::class,
            PersonGenerationRequested::class,
            TvSeriesGenerationRequested::class,
            TvShowGenerationRequested::class,
        ];

        $found = false;
        foreach ($eventTypes as $eventType) {
            $dispatched = Event::dispatched($eventType);
            foreach ($dispatched as $event) {
                if (isset($event[0]) && $event[0]->contextTag === $contextTag) {
                    $found = true;
                    break 2;
                }
            }
        }

        $this->assertTrue($found, "No event with context_tag '{$contextTag}' was dispatched");

        return $this;
    }
}
