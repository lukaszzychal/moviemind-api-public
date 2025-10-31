<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Listeners\QueueMovieGenerationJob;
use App\Listeners\QueuePersonGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_generation_requested_event_has_listener(): void
    {
        Event::fake();

        event(new MovieGenerationRequested('the-matrix', 'test-job-123'));

        Event::assertDispatched(MovieGenerationRequested::class);
        Event::assertListening(
            MovieGenerationRequested::class,
            QueueMovieGenerationJob::class
        );
    }

    public function test_person_generation_requested_event_has_listener(): void
    {
        Event::fake();

        event(new PersonGenerationRequested('keanu-reeves', 'test-job-123'));

        Event::assertDispatched(PersonGenerationRequested::class);
        Event::assertListening(
            PersonGenerationRequested::class,
            QueuePersonGenerationJob::class
        );
    }
}
