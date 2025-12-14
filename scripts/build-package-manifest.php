#!/usr/bin/env php
<?php

/**
 * Direct package manifest builder that doesn't require Laravel container
 *
 * This script builds the Laravel package manifest directly without going
 * through the PackageDiscoverCommand, which requires the Laravel container
 * to be initialized. This avoids the "Call to a member function make() on null"
 * error that occurs when the container isn't ready.
 *
 * Issue: https://github.com/lukaszzychal/phpstan-fixer/issues/60
 * Tracked in: TASK-049
 */

declare(strict_types=1);

$basePath = __DIR__ . '/../api';
$vendorPath = $basePath . '/vendor';
$manifestPath = $basePath . '/bootstrap/cache/packages.php';

// Ensure bootstrap/cache directory exists
$cacheDir = dirname($manifestPath);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Load Composer's installed.json
$installedJsonPath = $vendorPath . '/composer/installed.json';
if (!file_exists($installedJsonPath)) {
    // No packages installed yet, create empty manifest
    file_put_contents($manifestPath, "<?php return [];\n");
    exit(0);
}

$installed = json_decode(file_get_contents($installedJsonPath), true);
$packages = $installed['packages'] ?? $installed;

// Get packages to ignore from composer.json
$composerJsonPath = $basePath . '/composer.json';
$ignore = [];
if (file_exists($composerJsonPath)) {
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    $ignore = $composerJson['extra']['laravel']['dont-discover'] ?? [];
}

$ignoreAll = in_array('*', $ignore);

// Build manifest
$manifest = [];
foreach ($packages as $package) {
    $packageName = $package['name'];
    $configuration = $package['extra']['laravel'] ?? [];
    
    // Merge dont-discover from package configuration
    if (isset($configuration['dont-discover'])) {
        $packageDontDiscover = $configuration['dont-discover'];
        // Handle both array and boolean (for compatibility)
        if (is_array($packageDontDiscover)) {
            $ignore = array_merge($ignore, $packageDontDiscover);
        }
    }
    
    // Check if package should be ignored
    if ($ignoreAll || in_array($packageName, $ignore, true)) {
        continue;
    }
    
    // Only include packages with Laravel configuration
    if (!empty($configuration)) {
        $manifest[$packageName] = $configuration;
    }
}

// Write manifest
$manifestContent = "<?php return " . var_export($manifest, true) . ";\n";
file_put_contents($manifestPath, $manifestContent);

exit(0);

