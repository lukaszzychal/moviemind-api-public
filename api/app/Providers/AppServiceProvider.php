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
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind OpenAI Client
        $this->app->bind(OpenAiClientInterface::class, OpenAiClient::class);

        // Bind Entity Verification Service
        $this->app->bind(EntityVerificationServiceInterface::class, TmdbVerificationService::class);

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
        // Configure feature flags per instance from environment variables
        // This enables Modular Monolith with Feature-Based Instance Scaling
        // @phpstan-ignore-next-line - Instance ID is instance-specific and cannot be cached
        $instanceId = env('INSTANCE_ID');

        if ($instanceId) {
            $flags = config('pennant.flags', []);

            foreach ($flags as $name => $config) {
                // Convert flag name to environment variable name
                // e.g., 'ai_description_generation' -> 'FEATURE_AI_DESCRIPTION_GENERATION'
                $envKey = 'FEATURE_'.strtoupper(str_replace(['-', '_'], '_', $name));
                // @phpstan-ignore-next-line - Feature flags per instance are instance-specific and cannot be cached
                $envValue = env($envKey);

                // If environment variable is set, override the feature flag for this instance
                if ($envValue !== null) {
                    $isActive = filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
                    Feature::for('instance:'.$instanceId)->activate($name, $isActive);
                }
            }
        }
    }
}
