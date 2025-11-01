<?php

namespace App\Helpers;

class AiServiceSelector
{
    private const MOCK = 'mock';

    private const REAL = 'real';

    /**
     * Get the configured AI service (mock or real).
     */
    public static function getService(): string
    {
        return config('services.ai.service', self::MOCK);
    }

    /**
     * Check if real AI service is configured.
     */
    public static function isReal(): bool
    {
        return self::getService() === self::REAL;
    }

    /**
     * Check if mock AI service is configured.
     */
    public static function isMock(): bool
    {
        return self::getService() === self::MOCK;
    }

    /**
     * Validate that the configured service is valid.
     *
     * @throws \InvalidArgumentException
     */
    public static function validate(): void
    {
        $service = self::getService();
        if (! in_array($service, [self::MOCK, self::REAL], true)) {
            throw new \InvalidArgumentException("Invalid AI service: {$service}. Must be ".self::MOCK.' or '.self::REAL.'.');
        }
    }
}
