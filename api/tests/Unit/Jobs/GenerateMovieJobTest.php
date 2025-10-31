<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_job_marks_existing_movie_as_done(): void
    {
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);
        $jobId = 'test-job-123';

        $job = new MockGenerateMovieJob('the-matrix', $jobId);
        $job->handle();

        // Movie should still exist (not duplicated)
        $this->assertDatabaseCount('movies', 1);
        $this->assertEquals($movie->id, Movie::where('slug', 'the-matrix')->first()->id);

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($movie->id, $cached['id']);
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

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($movie->id, $cached['id']);
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
