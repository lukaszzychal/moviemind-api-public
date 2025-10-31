<?php

namespace Tests\Unit\Events;

use App\Events\PersonGenerationRequested;
use Tests\TestCase;

class PersonGenerationRequestedTest extends TestCase
{
    public function test_event_has_correct_properties(): void
    {
        $slug = 'keanu-reeves';
        $jobId = 'test-job-123';

        $event = new PersonGenerationRequested($slug, $jobId);

        $this->assertEquals($slug, $event->slug);
        $this->assertEquals($jobId, $event->jobId);
    }

    public function test_event_can_be_serialized(): void
    {
        $event = new PersonGenerationRequested('keanu-reeves', 'test-job-123');

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertEquals($event->slug, $unserialized->slug);
        $this->assertEquals($event->jobId, $unserialized->jobId);
    }
}
