<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case RESOLVED = 'resolved';
    case REJECTED = 'rejected';

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
            self::PENDING => 'Pending',
            self::VERIFIED => 'Verified',
            self::RESOLVED => 'Resolved',
            self::REJECTED => 'Rejected',
        };
    }
}
