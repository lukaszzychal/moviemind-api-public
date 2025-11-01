<?php

namespace App\Helpers;

class AiServiceSelector
{
    /**
     * Get the configured AI service (mock or real).
     */
    public static function getService(): string
    {
        return config('services.ai.service', 'mock');
    }

    /**
     * Check if real AI service is configured.
     */
    public static function isReal(): bool
    {
        return self::getService() === 'real';
    }

    /**
     * Check if mock AI service is configured.
     */
    public static function isMock(): bool
    {
        return self::getService() === 'mock';
    }

    /**
     * Validate that the configured service is valid.
     *
     * @throws \InvalidArgumentException
     */
    public static function validate(): void
    {
        $service = self::getService();
        if (! in_array($service, ['mock', 'real'])) {
            throw new \InvalidArgumentException("Invalid AI service: {$service}. Must be 'mock' or 'real'.");
        }
    }
}
