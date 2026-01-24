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
        $this->definitions = $definitions ?? config('pennant.metadata', []);
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
                    'togglable' => $flag['togglable'] ?? false,
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

        return (bool) ($meta['togglable'] ?? false);
    }

    public function set(string $name, bool $activate): void
    {
        // Use default scope (null) to match application code (Feature::active() without for())
        if ($activate) {
            Feature::activate($name);
        } else {
            Feature::deactivate($name);
        }
    }

    public function reset(string $name): void
    {
        Feature::forget($name);
    }

    public function isActive(string $name): bool
    {
        // Use default scope (null) to match application code (Feature::active() without for())
        return (bool) Feature::active($name);
    }
}
