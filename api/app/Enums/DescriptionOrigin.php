<?php

namespace App\Enums;

enum DescriptionOrigin: string
{
    case GENERATED = 'GENERATED';
    case TRANSLATED = 'TRANSLATED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}

