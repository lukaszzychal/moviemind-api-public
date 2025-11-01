<?php

namespace App\Providers;

use App\Services\OpenAiClient;
use App\Services\OpenAiClientInterface;
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
