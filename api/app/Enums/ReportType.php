<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportType: string
{
    case INCORRECT_INFO = 'incorrect_info';
    case GRAMMAR_ERROR = 'grammar_error';
    case FACTUAL_ERROR = 'factual_error';
    case INCOMPLETE = 'incomplete';
    case INAPPROPRIATE = 'inappropriate';
    case OTHER = 'other';

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
        return match ($this) {
            self::INCORRECT_INFO => 'Incorrect Information',
            self::GRAMMAR_ERROR => 'Grammar Error',
            self::FACTUAL_ERROR => 'Factual Error',
            self::INCOMPLETE => 'Incomplete',
            self::INAPPROPRIATE => 'Inappropriate',
            self::OTHER => 'Other',
        };
    }

    /**
     * Get weight for priority score calculation.
     * Higher weight = higher priority.
     */
    public function weight(): float
    {
        return match ($this) {
            self::FACTUAL_ERROR => 3.0,
            self::INAPPROPRIATE => 2.5,
            self::INCORRECT_INFO => 2.0,
            self::GRAMMAR_ERROR => 1.5,
            self::INCOMPLETE => 1.0,
            self::OTHER => 0.5,
        };
    }
}
