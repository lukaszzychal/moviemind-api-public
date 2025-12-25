<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Service for managing API keys.
 */
class ApiKeyService
{
    /**
     * Generate a secure API key.
     *
     * @return string The plaintext API key (to be shown to user once, then hashed)
     */
    public function generateKey(): string
    {
        // Generate a secure random key
        // Format: mm_ prefix + 40 random characters = 43 characters total
        $randomBytes = random_bytes(30);
        $key = 'mm_'.Str::replace(['/', '+', '='], ['-', '_', ''], base64_encode($randomBytes));

        return $key;
    }

    /**
     * Extract the prefix from an API key (first 8 characters after mm_).
     *
     * @return string The key prefix
     */
    public function extractPrefix(string $plaintextKey): string
    {
        // Prefix is the first 8 characters after "mm_"
        if (strlen($plaintextKey) < 11) { // mm_ + 8 chars = 11 minimum
            return substr($plaintextKey, 0, 8);
        }

        return substr($plaintextKey, 3, 8); // Skip "mm_" and take next 8 chars
    }

    /**
     * Hash an API key for storage.
     */
    public function hashKey(string $plaintextKey): string
    {
        return Hash::make($plaintextKey);
    }

    /**
     * Validate an API key against the stored hash.
     */
    public function validateKey(string $plaintextKey, string $hashedKey): bool
    {
        return Hash::check($plaintextKey, $hashedKey);
    }

    /**
     * Create a new API key.
     *
     * @param  string  $name  Name/description for the key
     * @param  string|null  $planId  Subscription plan ID
     * @param  string|null  $userId  User ID (for future use)
     * @param  \DateTimeInterface|null  $expiresAt  Expiration date
     * @return array{key: string, apiKey: ApiKey} The plaintext key (shown once) and the ApiKey model
     */
    public function createKey(
        string $name,
        ?string $planId = null,
        ?string $userId = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $plaintextKey = $this->generateKey();
        $hashedKey = $this->hashKey($plaintextKey);
        $keyPrefix = $this->extractPrefix($plaintextKey);

        $apiKey = ApiKey::create([
            'key' => $hashedKey,
            'key_prefix' => $keyPrefix,
            'name' => $name,
            'plan_id' => $planId,
            'user_id' => $userId,
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        return [
            'key' => $plaintextKey,
            'apiKey' => $apiKey,
        ];
    }

    /**
     * Find an API key by its plaintext key.
     *
     * @return ApiKey|null The API key if found and valid, null otherwise
     */
    public function findKeyByPlaintext(string $plaintextKey): ?ApiKey
    {
        $keyPrefix = $this->extractPrefix($plaintextKey);

        // First, find candidates by prefix (much faster than checking all keys)
        $candidates = ApiKey::where('key_prefix', $keyPrefix)
            ->where('is_active', true)
            ->get();

        // Then verify each candidate using hash comparison
        foreach ($candidates as $apiKey) {
            if ($this->validateKey($plaintextKey, $apiKey->key)) {
                return $apiKey;
            }
        }

        return null;
    }

    /**
     * Get the subscription plan ID for an API key.
     *
     * @return string|null The plan ID, or null if not found
     */
    public function getKeyPlan(string $plaintextKey): ?string
    {
        $apiKey = $this->findKeyByPlaintext($plaintextKey);

        return $apiKey?->plan_id;
    }

    /**
     * Track API key usage (update last_used_at).
     */
    public function trackUsage(string $plaintextKey, ?string $endpoint = null): void
    {
        $apiKey = $this->findKeyByPlaintext($plaintextKey);

        if ($apiKey !== null) {
            $apiKey->markAsUsed();
        }
    }

    /**
     * Validate that an API key exists, is active, and not expired.
     *
     * @return ApiKey|null The API key if valid, null otherwise
     */
    public function validateAndGetKey(string $plaintextKey): ?ApiKey
    {
        $apiKey = $this->findKeyByPlaintext($plaintextKey);

        if ($apiKey === null) {
            return null;
        }

        if (! $apiKey->isValid()) {
            return null;
        }

        return $apiKey;
    }
}
