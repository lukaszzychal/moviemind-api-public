<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MockGeneratePersonJob;
use App\Jobs\RealGeneratePersonJob;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GeneratePersonJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_job_has_correct_properties(): void
    {
        $slug = 'keanu-reeves';
        $jobId = 'test-job-123';

        $job = new MockGeneratePersonJob($slug, $jobId);

        $this->assertEquals($slug, $job->slug);
        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(90, $job->timeout);
    }

    public function test_real_job_has_correct_properties(): void
    {
        $slug = 'keanu-reeves';
        $jobId = 'test-job-123';

        $job = new RealGeneratePersonJob($slug, $jobId);

        $this->assertEquals($slug, $job->slug);
        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout); // Longer timeout for real API
    }

    public function test_job_marks_existing_person_as_done(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);
        $jobId = 'test-job-123';

        $job = new MockGeneratePersonJob('keanu-reeves', $jobId);
        $job->handle();

        // Person should still exist (not duplicated)
        $this->assertDatabaseCount('people', 1);
        $this->assertEquals($person->id, Person::where('slug', 'keanu-reeves')->first()->id);

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($person->id, $cached['id']);
    }

    public function test_mock_job_implements_should_queue(): void
    {
        $job = new MockGeneratePersonJob('test', 'job-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_real_job_implements_should_queue(): void
    {
        $job = new RealGeneratePersonJob('test', 'job-123');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
