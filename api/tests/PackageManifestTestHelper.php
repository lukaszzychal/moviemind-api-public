<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

/**
 * Helper to build package manifest before Laravel initialization
 *
 * This helper builds the package manifest directly without requiring
 * the Laravel container, preventing the "Call to a member function make() on null"
 * error during test initialization.
 *
 * Issue: https://github.com/lukaszzychal/phpstan-fixer/issues/60
 * Tracked in: TASK-049
 */
class PackageManifestTestHelper
{
    /**
     * Build the package manifest before Laravel initialization
     */
    public static function buildManifest(string $basePath): void
    {
        $manifestPath = $basePath.'/bootstrap/cache/packages.php';
        $cacheDir = dirname($manifestPath);

        // Ensure bootstrap/cache directory exists
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // If manifest already exists and is valid, skip building
        if (file_exists($manifestPath)) {
            try {
                $manifest = require $manifestPath;
                if (is_array($manifest)) {
                    return; // Manifest exists and is valid
                }
            } catch (\Throwable $e) {
                // Manifest is invalid, rebuild it
            }
        }

        // Build manifest using PackageManifest directly (no container required)
        $manifest = new PackageManifest(
            new Filesystem,
            $basePath,
            $manifestPath
        );

        $manifest->build();
    }
}
