<?php

namespace Tests\Unit\Events;

use App\Events\MovieGenerationRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieGenerationRequestedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_has_correct_properties(): void
    {
        $slug = 'the-matrix';
        $jobId = 'test-job-123';

        $event = new MovieGenerationRequested($slug, $jobId);

        $this->assertEquals($slug, $event->slug);
        $this->assertEquals($jobId, $event->jobId);
    }

    public function test_event_can_be_serialized(): void
    {
        $event = new MovieGenerationRequested('the-matrix', 'test-job-123');

        // Event should be serializable (for queue)
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertEquals($event->slug, $unserialized->slug);
        $this->assertEquals($event->jobId, $unserialized->jobId);
    }
}
