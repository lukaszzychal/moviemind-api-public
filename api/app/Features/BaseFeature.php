<?php

declare(strict_types=1);

namespace App\Features;

use Illuminate\Support\Str;

abstract class BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        $flag = $this->flagName();

        // 1. Check Hard Constraint (Force)
        // If 'force' is explicitly set in config (via ENV), it overrides everything.
        $force = config("features.{$flag}.force");

        if ($force !== null && $force !== '') {
            return filter_var($force, FILTER_VALIDATE_BOOL);
        }

        // 2. Check Soft Default
        // If no force value, use the default from config (which comes from ENV 'default' or fallback).
        // Pennant uses this return value ONLY if the value is not found in the Store (DB).
        return config("features.{$flag}.default",
            config("pennant.metadata.{$flag}.default", false) // Fallback to old pennant config for backward compatibility during migration
        );
    }

    protected function flagName(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
