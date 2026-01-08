<?php

namespace App\Providers;

use App\Events\MovieGenerationRequested;
use App\Events\PersonGenerationRequested;
use App\Events\TvSeriesGenerationRequested;
use App\Events\TvShowGenerationRequested;
use App\Listeners\QueueMovieGenerationJob;
use App\Listeners\QueuePersonGenerationJob;
use App\Listeners\QueueTvSeriesGenerationJob;
use App\Listeners\QueueTvShowGenerationJob;
use App\Listeners\SendOutgoingWebhookListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        MovieGenerationRequested::class => [
            QueueMovieGenerationJob::class,
            SendOutgoingWebhookListener::class,
        ],
        PersonGenerationRequested::class => [
            QueuePersonGenerationJob::class,
            SendOutgoingWebhookListener::class,
        ],
        TvSeriesGenerationRequested::class => [
            QueueTvSeriesGenerationJob::class,
        ],
        TvShowGenerationRequested::class => [
            QueueTvShowGenerationJob::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
