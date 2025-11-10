<?php

declare(strict_types=1);

namespace App\Features;

use Illuminate\Support\Str;

abstract class BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        $flag = $this->flagName();

        return config("pennant.flags.{$flag}.default", false);
    }

    protected function flagName(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
