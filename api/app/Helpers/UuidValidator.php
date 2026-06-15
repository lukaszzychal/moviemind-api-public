<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Centralized UUID validation helper.
 *
 * Replaces duplicated UUID validation logic across controllers
 * (MovieController, PersonController, TvSeriesController, TvShowController).
 */
class UuidValidator
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Normalize a UUID parameter from request.
     *
     * Returns:
     * - null if value is empty (not provided)
     * - string if value is a valid UUID
     * - false if value is invalid
     */
    public static function normalize(mixed $value): string|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        $uuid = (string) $value;

        if (! self::isValid($uuid)) {
            return false;
        }

        return $uuid;
    }

    /**
     * Check if a string is a valid UUID format.
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match(self::UUID_PATTERN, $uuid) === 1;
    }
}
