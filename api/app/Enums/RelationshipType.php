<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Movie relationship types.
 *
 * @author MovieMind API Team
 */
enum RelationshipType: string
{
    case SEQUEL = 'SEQUEL';
    case PREQUEL = 'PREQUEL';
    case REMAKE = 'REMAKE';
    case SERIES = 'SERIES';
    case SPINOFF = 'SPINOFF';
    case SAME_UNIVERSE = 'SAME_UNIVERSE';

    /**
     * Get human-readable label for relationship type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SEQUEL => 'Sequel',
            self::PREQUEL => 'Prequel',
            self::REMAKE => 'Remake',
            self::SERIES => 'Series',
            self::SPINOFF => 'Spinoff',
            self::SAME_UNIVERSE => 'Same Universe',
        };
    }
}
