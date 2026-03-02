<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $flags = config('features', []);

        foreach ($flags as $name => $metadata) {
            $force = $metadata['force'] ?? null;

            if ($force !== null && $force !== '') {
                // FORCE logic:
                // If a force value is present, we treat it as the absolute source of truth.
                // We resolve the boolean value of the force config.
                $forcedValue = filter_var($force, FILTER_VALIDATE_BOOLEAN);

                // We explicitly set the feature state in Pennant to match the forced value.
                // This ensures that Feature::active($name) returns the correct value
                // and effectively 'overwrites' whatever might be in the database.
                if ($forcedValue) {
                    Feature::activate($name);
                } else {
                    Feature::deactivate($name);
                }
            }

            // Define the default value logic.
            // Note: Pennant uses this only if the feature has no value in storage.
            // If we just forced it above, storage now HAS a value, so this closure won't be used
            // for the current request, which is correct.
            Feature::define($name, fn () => $metadata['default'] ?? false);
        }
    }
}
