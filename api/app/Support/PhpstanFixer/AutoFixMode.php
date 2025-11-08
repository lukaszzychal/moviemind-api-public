<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer;

enum AutoFixMode: string
{
    case SUGGEST = 'suggest';
    case APPLY = 'apply';

    public static function fromString(?string $value): self
    {
        return match ($value) {
            self::APPLY->value => self::APPLY,
            default => self::SUGGEST,
        };
    }
}
