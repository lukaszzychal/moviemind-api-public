<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class GenerateMovieJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_job_has_correct_properties(): void
    {
        $slug = 'the-matrix';
        $jobId = 'test-job-123';

        $job = new MockGenerateMovieJob($slug, $jobId);

        $this->assertEquals($slug, $job->slug);
        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(90, $job->timeout);
    }

    public function test_real_job_has_correct_properties(): void
    {
        $slug = 'the-matrix';
        $jobId = 'test-job-123';

        $job = new RealGenerateMovieJob($slug, $jobId);

        $this->assertEquals($slug, $job->slug);
        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout); // Longer timeout for real API
    }

    public function test_real_job_backoff_uses_configuration(): void
    {
        Config::set('services.openai.backoff.enabled', true);
        Config::set('services.openai.backoff.intervals', [5, 15, 30]);

        $job = new RealGenerateMovieJob('test-slug', 'job-1');

        $this->assertSame([5, 15, 30], $job->backoff());
    }

    public function test_real_job_backoff_can_be_disabled(): void
    {
        Config::set('services.openai.backoff.enabled', false);
        Config::set('services.openai.backoff.intervals', [5, 15, 30]);

        $job = new RealGenerateMovieJob('test-slug', 'job-1');

        $this->assertSame([], $job->backoff());
    }

    public function test_job_appends_description_for_existing_movie(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);
        $originalDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Original seeded description.',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $movie->default_description_id = $originalDescription->id;
        $movie->save();
        $jobId = 'test-job-123';

        $job = new MockGenerateMovieJob('the-matrix', $jobId, baselineDescriptionId: $originalDescription->id);
        $job->handle();

        $movie->refresh();

        // Movie should still exist (not duplicated) and have new default description
        $this->assertDatabaseCount('movies', 1);
        $this->assertEquals(2, $movie->descriptions()->count());
        $this->assertNotEquals($originalDescription->id, $movie->default_description_id);
        $tags = $movie->descriptions()
            ->pluck('context_tag')
            ->map(fn ($tag) => $tag instanceof \BackedEnum ? $tag->value : (string) $tag)
            ->all();
        $this->assertCount(count($tags), array_unique($tags), 'Expected unique context_tag per description');

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($movie->id, $cached['id']);
        $this->assertNotNull($cached['description_id']);
        $this->assertEquals($movie->default_description_id, $cached['description_id']);
    }

    public function test_job_updates_baseline_when_locking_enabled(): void
    {
        Feature::activate('ai_generation_baseline_locking');

        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);
        $originalDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Original seeded description.',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $movie->default_description_id = $originalDescription->id;
        $movie->save();

        (new MockGenerateMovieJob('the-matrix', 'job-locking', baselineDescriptionId: $originalDescription->id))->handle();

        $movie->refresh();

        $this->assertDatabaseCount('movies', 1);
        $this->assertEquals(1, $movie->descriptions()->count());
        $this->assertEquals($originalDescription->id, $movie->default_description_id);
        $this->assertNotSame('Original seeded description.', $movie->descriptions()->first()->text);

        Feature::deactivate('ai_generation_baseline_locking');
    }

    public function test_job_creates_movie_when_not_exists(): void
    {
        $jobId = 'test-job-123';

        $job = new MockGenerateMovieJob('new-movie-test', $jobId);
        $job->handle();

        // Verify movie was created
        // Note: Movie::generateSlug() is now used, so slug may differ from requested slug
        // The slug will be generated from parsed title, year (1999 default), and director
        // We need to find the movie by checking what was actually created
        $this->assertDatabaseCount('movies', 1);
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should be created');

        // Verify the slug was generated (not just the requested slug)
        // Note: generateSlug may add director suffix if base slug exists in database
        $parsed = Movie::parseSlug('new-movie-test');
        $title = $parsed['title'] ?? 'New Movie Test';
        $year = $parsed['year'] ?? 1999;
        $director = $parsed['director'] ?? 'Mock AI Director';

        // The slug should start with the base slug format
        $this->assertStringStartsWith('new-movie-test-1999', $movie->slug, 'Movie slug should start with base format');

        // Verify it was generated using generateSlug
        // Note: The actual slug may include director if base slug "new-movie-test-1999" already exists
        // We verify by checking what generateSlug would produce excluding the current movie
        // This simulates what would happen when creating a new movie (before it exists)
        $expectedSlug = Movie::generateSlug($title, $year, $director, $movie->id);
        $this->assertEquals($expectedSlug, $movie->slug, 'Movie slug should match generated slug (excluding current movie from check)');
        $this->assertDatabaseCount('movies', 1);
        $this->assertEquals(1, $movie->descriptions()->count());
        $this->assertEquals(
            $movie->default_description_id,
            $movie->descriptions()->first()->id
        );
        $this->assertSame('en-US', $movie->descriptions()->first()->locale->value);

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($movie->id, $cached['id']);
        $this->assertEquals($movie->default_description_id, $cached['description_id']);
        $this->assertEquals('en-US', $cached['locale']);
        $this->assertEquals('DEFAULT', $cached['context_tag']);
        $this->assertEquals('new-movie-test', $cached['requested_slug']);
    }

    public function test_job_respects_provided_locale_and_context(): void
    {
        $jobId = 'test-job-456';

        $job = new MockGenerateMovieJob('localized-movie', $jobId, locale: 'pl-PL', contextTag: 'critical');
        $job->handle();

        // Note: Movie::generateSlug() is now used, so slug may differ from requested slug
        // Find the movie that was created
        $this->assertDatabaseCount('movies', 1);
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Movie should be created');

        // Verify the slug was generated correctly
        // Note: generateSlug may add director suffix if base slug exists in database
        $parsed = Movie::parseSlug('localized-movie');
        $title = $parsed['title'] ?? 'Localized Movie';
        $year = $parsed['year'] ?? 1999;
        $director = $parsed['director'] ?? 'Mock AI Director';

        // The slug should start with the base slug format
        $this->assertStringStartsWith('localized-movie-1999', $movie->slug, 'Movie slug should start with base format');

        // Verify it was generated using generateSlug
        // Note: We need to check what generateSlug would produce BEFORE the movie was created
        // Since the movie now exists, we need to exclude it from the check
        // The actual slug should match what generateSlug would produce when creating a new movie
        $expectedSlug = Movie::generateSlug($title, $year, $director, $movie->id);
        $this->assertEquals($expectedSlug, $movie->slug, 'Movie slug should match generated slug (excluding current movie)');
        $description = $movie->descriptions()->first();
        $this->assertSame('pl-PL', $description->locale->value);
        $contextValue = $description->context_tag instanceof \BackedEnum ? $description->context_tag->value : (string) $description->context_tag;
        $this->assertSame('critical', $contextValue);

        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('pl-PL', $cached['locale']);
        $this->assertEquals('critical', $cached['context_tag']);
    }

    public function test_mock_job_implements_should_queue(): void
    {
        $job = new MockGenerateMovieJob('test', 'job-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_real_job_implements_should_queue(): void
    {
        $job = new RealGenerateMovieJob('test', 'job-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_subsequent_job_does_not_override_default_if_baseline_has_changed(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);
        $baseline = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Baseline description',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $movie->default_description_id = $baseline->id;
        $movie->save();

        $firstJob = new MockGenerateMovieJob(
            'the-matrix',
            'job-first',
            baselineDescriptionId: $baseline->id
        );
        $firstJob->handle();

        $movie->refresh();
        $movie->load('defaultDescription');
        $firstDefault = $movie->default_description_id;

        $secondJob = new MockGenerateMovieJob(
            'the-matrix',
            'job-second',
            baselineDescriptionId: $baseline->id
        );
        $secondJob->handle();

        $movie->refresh();
        $this->assertEquals(3, $movie->descriptions()->count());
        $this->assertEquals($firstDefault, $movie->default_description_id);
        $secondPayload = Cache::get('ai_job:job-second');
        $this->assertNotNull($secondPayload);
        $this->assertSame('DONE', $secondPayload['status']);
        $this->assertNotEquals(
            $firstDefault,
            $secondPayload['description_id'],
            'Second job should record alternative description'
        );
    }

    public function test_subsequent_job_updates_same_baseline_when_locking_enabled(): void
    {
        Feature::activate('ai_generation_baseline_locking');

        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);
        $baseline = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Baseline description',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $movie->default_description_id = $baseline->id;
        $movie->save();

        (new MockGenerateMovieJob(
            'the-matrix',
            'job-lock-first',
            baselineDescriptionId: $baseline->id
        ))->handle();

        $movie->refresh();
        $initialText = $movie->descriptions()->first()->text;

        sleep(1);

        (new MockGenerateMovieJob(
            'the-matrix',
            'job-lock-second',
            baselineDescriptionId: $baseline->id
        ))->handle();

        $movie->refresh();

        $this->assertEquals(1, $movie->descriptions()->count());
        $this->assertNotSame($initialText, $movie->descriptions()->first()->text);
        $this->assertEquals($baseline->id, $movie->default_description_id);

        Feature::deactivate('ai_generation_baseline_locking');
    }
}
