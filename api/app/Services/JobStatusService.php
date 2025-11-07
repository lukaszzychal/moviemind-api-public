<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class JobStatusService
{
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Initialize job status in cache.
     */
    public function initializeStatus(
        string $jobId,
        string $entityType,
        string $slug,
        ?float $confidence = null
    ): void {
        Cache::put(
            $this->cacheKey($jobId),
            [
                'job_id' => $jobId,
                'status' => 'PENDING',
                'entity' => $entityType,
                'slug' => $slug,
                'confidence' => $confidence,
            ],
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }

    /**
     * Retrieve job status from cache.
     */
    public function getStatus(string $jobId): ?array
    {
        return Cache::get($this->cacheKey($jobId));
    }

    /**
     * Update existing job status payload.
     */
    public function updateStatus(string $jobId, array $payload): void
    {
        $existing = $this->getStatus($jobId);

        if ($existing === null) {
            return;
        }

        Cache::put(
            $this->cacheKey($jobId),
            array_merge($existing, $payload),
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }

    /**
     * Mark job as completed.
     */
    public function markDone(string $jobId, string $entityType, int $entityId, ?string $slug = null): void
    {
        $payload = [
            'status' => 'DONE',
            'entity' => $entityType,
            'entity_id' => $entityId,
        ];

        if ($slug !== null) {
            $payload['slug'] = $slug;
        }

        $this->updateStatus($jobId, $payload);
    }

    /**
     * Mark job as failed with optional error message.
     */
    public function markFailed(string $jobId, string $entityType, ?string $error = null): void
    {
        $payload = [
            'status' => 'FAILED',
            'entity' => $entityType,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        $this->updateStatus($jobId, $payload);
    }

    private function cacheKey(string $jobId): string
    {
        return "ai_job:{$jobId}";
    }
}
