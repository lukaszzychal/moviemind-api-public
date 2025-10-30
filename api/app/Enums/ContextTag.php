<?php

namespace App\Enums;

enum ContextTag: string
{
    case DEFAULT = 'DEFAULT';
    case MODERN = 'modern';
    case CRITICAL = 'critical';
    case HUMOROUS = 'humorous';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public function label(): string
    {
        return match($this) {
            self::DEFAULT => 'Default',
            self::MODERN => 'Modern',
            self::CRITICAL => 'Critical',
            self::HUMOROUS => 'Humorous',
        };
    }
}

