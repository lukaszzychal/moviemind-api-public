<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

        $job = new MockGenerateMovieJob('the-matrix', $jobId);
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

    public function test_job_creates_movie_when_not_exists(): void
    {
        $jobId = 'test-job-123';

        $job = new MockGenerateMovieJob('new-movie-test', $jobId);
        $job->handle();

        // Verify movie was created
        $movie = Movie::where('slug', 'like', 'new-movie-test%')->first();
        $this->assertNotNull($movie);
        $this->assertDatabaseCount('movies', 1);
        $this->assertEquals(1, $movie->descriptions()->count());
        $this->assertEquals(
            $movie->default_description_id,
            $movie->descriptions()->first()->id
        );

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($movie->id, $cached['id']);
        $this->assertEquals($movie->default_description_id, $cached['description_id']);
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
}
