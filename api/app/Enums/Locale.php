<?php

namespace App\Enums;

enum Locale: string
{
    case EN_US = 'en-US';
    case PL_PL = 'pl-PL';
    case DE_DE = 'de-DE';
    case FR_FR = 'fr-FR';
    case ES_ES = 'es-ES';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public function language(): string
    {
        return match($this) {
            self::EN_US => 'English',
            self::PL_PL => 'Polish',
            self::DE_DE => 'German',
            self::FR_FR => 'French',
            self::ES_ES => 'Spanish',
        };
    }

    public function country(): string
    {
        return match($this) {
            self::EN_US => 'United States',
            self::PL_PL => 'Poland',
            self::DE_DE => 'Germany',
            self::FR_FR => 'France',
            self::ES_ES => 'Spain',
        };
    }
}

