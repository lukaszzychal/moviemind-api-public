<?php

declare(strict_types=1);

namespace App\Services\FeatureFlag;

use Laravel\Pennant\Feature;

class FeatureFlagManager
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions;

    public function __construct(?array $definitions = null)
    {
        $this->definitions = $definitions ?? config('features', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Get all flags with their active status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithStatus(): array
    {
        return collect($this->definitions)
            ->map(function ($flag, $name) {
                return [
                    'name' => $name,
                    'active' => $this->isActive($name),
                    'description' => $flag['description'] ?? '',
                    'category' => $flag['category'] ?? 'other',
                    'default' => $flag['default'] ?? false,
                    'togglable' => $this->isTogglable($name),
                    'forced' => ($flag['force'] ?? null) !== null && ($flag['force'] ?? null) !== '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->definitions[$name] ?? null;
    }

    public function isTogglable(string $name): bool
    {
        $meta = $this->get($name);

        if ($meta === null) {
            return false;
        }

        // If 'force' is present and not empty, it overrides everything and locks the flag.
        $force = $meta['force'] ?? null;
        if ($force !== null && $force !== '') {
            return false;
        }

        // Otherwise respect the 'togglable' key (default true)
        return (bool) ($meta['togglable'] ?? true);
    }

    public function set(string $name, bool $activate): void
    {
        if (! $this->isTogglable($name)) {
            // Silently ignore or throw exception?
            // "Zabrania zmiany za pomocą endpointa czy ui" -> Exception is better for API feedback
            throw new \RuntimeException("Feature flag '{$name}' is not togglable.");
        }

        // Use default scope (null) to match application code (Feature::active() without for())
        if ($activate) {
            Feature::activate($name);
        } else {
            Feature::deactivate($name);
        }
    }

    public function reset(string $name): void
    {
        if (! $this->isTogglable($name)) {
            throw new \RuntimeException("Feature flag '{$name}' is locked and cannot be reset.");
        }

        Feature::forget($name);
    }

    public function isActive(string $name): bool
    {
        // Use default scope (null) to match application code (Feature::active() without for())
        return (bool) Feature::active($name);
    }
}
