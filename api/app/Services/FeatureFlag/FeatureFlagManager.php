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
        $this->definitions = $definitions ?? config('pennant.flags', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->definitions;
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

    public function toggle(string $name, bool $activate): void
    {
        if ($activate) {
            Feature::activate($name);

            return;
        }

        Feature::deactivate($name);
    }

    public function isActive(string $name): bool
    {
        return (bool) Feature::active($name);
    }
}
