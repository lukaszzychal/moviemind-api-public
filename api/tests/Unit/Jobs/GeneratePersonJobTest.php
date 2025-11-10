<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MockGeneratePersonJob;
use App\Jobs\RealGeneratePersonJob;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

    public function test_real_person_job_backoff_uses_configuration(): void
    {
        Config::set('services.openai.backoff.enabled', true);
        Config::set('services.openai.backoff.intervals', [10, 20]);

        $job = new RealGeneratePersonJob('test-person', 'job-1');

        $this->assertSame([10, 20], $job->backoff());
    }

    public function test_real_person_job_backoff_can_be_disabled(): void
    {
        Config::set('services.openai.backoff.enabled', false);

        $job = new RealGeneratePersonJob('test-person', 'job-1');

        $this->assertSame([], $job->backoff());
    }

    public function test_job_appends_bio_for_existing_person(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);
        $originalBio = PersonBio::create([
            'person_id' => $person->id,
            'locale' => 'en-US',
            'text' => 'Original seeded biography.',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $person->default_bio_id = $originalBio->id;
        $person->save();
        $jobId = 'test-job-123';

        $job = new MockGeneratePersonJob('keanu-reeves', $jobId, baselineBioId: $originalBio->id);
        $job->handle();

        $person->refresh();

        // Person should still exist (not duplicated) and have refreshed bio
        $this->assertDatabaseCount('people', 1);
        $this->assertEquals(2, $person->bios()->count());
        $this->assertNotEquals($originalBio->id, $person->default_bio_id);
        $tags = $person->bios()
            ->pluck('context_tag')
            ->map(fn ($tag) => $tag instanceof \BackedEnum ? $tag->value : (string) $tag)
            ->all();
        $this->assertCount(count($tags), array_unique($tags), 'Expected unique context_tag per bio');

        // Verify cache was updated
        $cached = Cache::get('ai_job:'.$jobId);
        $this->assertNotNull($cached);
        $this->assertEquals('DONE', $cached['status']);
        $this->assertEquals($person->id, $cached['id']);
        $this->assertEquals($person->default_bio_id, $cached['bio_id']);
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

    public function test_subsequent_job_does_not_override_default_bio_if_baseline_has_changed(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);
        $baseline = PersonBio::create([
            'person_id' => $person->id,
            'locale' => 'en-US',
            'text' => 'Baseline bio',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);
        $person->default_bio_id = $baseline->id;
        $person->save();

        $firstJob = new MockGeneratePersonJob(
            'keanu-reeves',
            'job-first',
            baselineBioId: $baseline->id
        );
        $firstJob->handle();

        $person->refresh();
        $firstDefault = $person->default_bio_id;

        $secondJob = new MockGeneratePersonJob(
            'keanu-reeves',
            'job-second',
            baselineBioId: $baseline->id
        );
        $secondJob->handle();

        $person->refresh();

        $this->assertEquals(3, $person->bios()->count());
        $this->assertEquals($firstDefault, $person->default_bio_id);

        $secondPayload = Cache::get('ai_job:job-second');
        $this->assertNotNull($secondPayload);
        $this->assertSame('DONE', $secondPayload['status']);
        $this->assertNotEquals(
            $firstDefault,
            $secondPayload['bio_id'],
            'Second job should record alternative bio'
        );
    }
}
