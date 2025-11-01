<?php

namespace App\Providers;

use App\Helpers\AiServiceSelector;
use App\Services\AiServiceInterface;
use App\Services\MockAiService;
use App\Services\OpenAiClient;
use App\Services\OpenAiClientInterface;
use App\Services\RealAiService;
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

        // Bind AI Service based on configuration
        // Use 'mock' for local development/testing
        // Use 'real' for production with Events + Jobs architecture
        $this->app->bind(AiServiceInterface::class, function ($app) {
            $aiService = AiServiceSelector::getService();
            AiServiceSelector::validate();

            return match ($aiService) {
                'real' => $app->make(RealAiService::class),
                'mock' => $app->make(MockAiService::class),
                default => throw new \InvalidArgumentException("Invalid AI service: {$aiService}. Must be 'mock' or 'real'."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
