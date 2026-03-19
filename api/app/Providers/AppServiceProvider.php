<?php

namespace App\Providers;

use App\Services\EntityVerificationServiceInterface;
use App\Services\OpenAiClient;
use App\Services\OpenAiClientInterface;
use App\Services\TmdbVerificationService;
use App\Support\PhpstanFixer\AutoFixService;
use App\Support\PhpstanFixer\Fixers\CollectionGenericDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingParamDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingPropertyDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingReturnDocblockFixer;
use App\Support\PhpstanFixer\Fixers\UndefinedPivotPropertyFixer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind services...
        $this->app->bind(OpenAiClientInterface::class, OpenAiClient::class);
        $this->app->bind(EntityVerificationServiceInterface::class, TmdbVerificationService::class);

        // TV show/series search and retrieval now also use TMDb for consistency and better coverage
        // (Removing Tvmaze overrides to use default TmdbVerificationService)

        // Register AutoFixService - always bind to prevent dependency resolution errors
        $this->app->bind(
            AutoFixService::class,
            fn ($app) => new AutoFixService(
                $app->runningInConsole()
                    ? [] // Empty array in console to avoid issues
                    : [
                        $app->make(UndefinedPivotPropertyFixer::class),
                        $app->make(MissingParamDocblockFixer::class),
                        $app->make(MissingReturnDocblockFixer::class),
                        $app->make(MissingPropertyDocblockFixer::class),
                        $app->make(CollectionGenericDocblockFixer::class),
                    ]
            )
        );
    }

    public function boot(): void
    {
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }
    }
}
