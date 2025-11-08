<?php

namespace App\Providers;

use App\Services\OpenAiClient;
use App\Services\OpenAiClientInterface;
use App\Support\PhpstanFixer\AutoFixService;
use App\Support\PhpstanFixer\Fixers\CollectionGenericDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingParamDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingPropertyDocblockFixer;
use App\Support\PhpstanFixer\Fixers\MissingReturnDocblockFixer;
use App\Support\PhpstanFixer\Fixers\UndefinedPivotPropertyFixer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind OpenAI Client
        $this->app->bind(OpenAiClientInterface::class, OpenAiClient::class);

        $this->app->bind(
            AutoFixService::class,
            fn ($app) => new AutoFixService([
                $app->make(UndefinedPivotPropertyFixer::class),
                $app->make(MissingParamDocblockFixer::class),
                $app->make(MissingReturnDocblockFixer::class),
                $app->make(MissingPropertyDocblockFixer::class),
                $app->make(CollectionGenericDocblockFixer::class),
            ])
        );

        // Note: AiServiceInterface binding removed - all controllers now use Events
        // See: MovieController, PersonController, GenerateController - they all emit Events
        // which are handled by Listeners that dispatch Jobs based on AI_SERVICE config
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
