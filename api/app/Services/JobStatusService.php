<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class JobStatusService
{
    private const CACHE_TTL_MINUTES = 15;

    private const STATUS_PENDING = 'PENDING';

    private const GENERATION_SLOT_TTL_MINUTES = 15;

    private const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    private const STATUS_DONE = 'DONE';

    private const STATUS_FAILED = 'FAILED';

    /**
     * Initialize job status in cache.
     */
    public function initializeStatus(
        string $jobId,
        string $entityType,
        string $slug,
        ?float $confidence = null,
        ?string $locale = null,
        ?string $contextTag = null
    ): void {
        Cache::put(
            $this->cacheKey($jobId),
            [
                'job_id' => $jobId,
                'status' => self::STATUS_PENDING,
                'entity' => $entityType,
                'slug' => $slug,
                'requested_slug' => $slug,
                'confidence' => $confidence,
                'locale' => $locale,
                'context_tag' => $contextTag,
            ],
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );

        $this->storeSlugMapping($entityType, $slug, $jobId, self::STATUS_PENDING, $locale, $contextTag);
        Log::info('JobStatusService: initialized status', [
            'job_id' => $jobId,
            'entity' => $entityType,
            'slug' => $slug,
            'locale' => $locale,
            'context_tag' => $contextTag,
        ]);
    }

    /**
     * Try to acquire an in-flight generation slot. Returns true when the slot was acquired.
     */
    public function acquireGenerationSlot(
        string $entityType,
        string $slug,
        string $jobId,
        ?string $locale = null,
        ?string $contextTag = null
    ): bool {
        $key = $this->generationSlotKey($entityType, $slug, $this->stringOrNull($locale), $this->stringOrNull($contextTag));

        $acquired = Cache::add(
            $key,
            $jobId,
            Carbon::now()->addMinutes(self::GENERATION_SLOT_TTL_MINUTES)
        );

        if ($acquired) {
            Log::info('JobStatusService: acquired generation slot', [
                'slot_key' => $key,
                'job_id' => $jobId,
            ]);
        } else {
            Log::info('JobStatusService: generation slot busy', [
                'slot_key' => $key,
            ]);
        }

        return $acquired;
    }

    /**
     * Retrieve the job id currently occupying the generation slot.
     */
    public function currentGenerationSlotJobId(
        string $entityType,
        string $slug,
        ?string $locale = null,
        ?string $contextTag = null
    ): ?string {
        $key = $this->generationSlotKey($entityType, $slug, $this->stringOrNull($locale), $this->stringOrNull($contextTag));
        /** @var string|null $jobId */
        $jobId = Cache::get($key);

        if ($jobId !== null) {
            Log::info('JobStatusService: generation slot holder', [
                'slot_key' => $key,
                'job_id' => $jobId,
            ]);
        }

        return $jobId;
    }

    /**
     * Release the slot after job completion or failure.
     */
    public function releaseGenerationSlot(
        string $entityType,
        string $slug,
        ?string $locale = null,
        ?string $contextTag = null
    ): void {
        $key = $this->generationSlotKey($entityType, $slug, $this->stringOrNull($locale), $this->stringOrNull($contextTag));
        Cache::forget($key);
        Log::info('JobStatusService: released generation slot', [
            'slot_key' => $key,
        ]);
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

        $merged = array_merge($existing, $payload);

        Cache::put(
            $this->cacheKey($jobId),
            $merged,
            Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
        );

        $entityType = (string) ($merged['entity'] ?? '');
        $requestedSlug = (string) ($merged['requested_slug'] ?? ($merged['slug'] ?? ''));
        $status = (string) ($merged['status'] ?? '');
        $locale = $merged['locale'] ?? null;
        $contextTag = $merged['context_tag'] ?? null;

        if ($entityType !== '' && $requestedSlug !== '') {
            $this->storeSlugMapping($entityType, $requestedSlug, $jobId, $status, $this->stringOrNull($locale), $this->stringOrNull($contextTag));
        }
        Log::info('JobStatusService: updated status', [
            'job_id' => $jobId,
            'status' => $status,
            'locale' => $locale,
            'context_tag' => $contextTag,
        ]);
    }

    /**
     * Mark job as completed.
     */
    public function markDone(string $jobId, string $entityType, int $entityId, ?string $slug = null): void
    {
        $payload = [
            'status' => self::STATUS_DONE,
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
            'status' => self::STATUS_FAILED,
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

    /**
     * Attempt to find an active (pending/in-progress) job for the given entity slug.
     *
     * @return array{job_id: string, status: string, entity: string, slug: string, confidence: mixed}|null
     */
    public function findActiveJobForSlug(string $entityType, string $slug, ?string $locale = null, ?string $contextTag = null): ?array
    {
        $slugKeys = array_unique(array_filter([
            $this->slugCacheKey($entityType, $slug, $this->stringOrNull($locale), $this->stringOrNull($contextTag)),
            $this->slugCacheKey($entityType, $slug),
        ]));

        foreach ($slugKeys as $slugKey) {
            $jobId = Cache::get($slugKey);

            if (! $jobId) {
                Log::info('JobStatusService: slug mapping empty', ['slug_key' => $slugKey]);

                continue;
            }

            $status = $this->getStatus($jobId);

            if ($status === null) {
                Cache::forget($slugKey);
                Log::info('JobStatusService: cleared stale mapping', ['slug_key' => $slugKey, 'job_id' => $jobId]);

                continue;
            }

            $currentStatus = (string) ($status['status'] ?? '');

            if (! $this->isActiveStatus($currentStatus)) {
                Cache::forget($slugKey);
                Log::info('JobStatusService: removed inactive mapping', [
                    'slug_key' => $slugKey,
                    'job_id' => $jobId,
                    'status' => $currentStatus,
                ]);

                continue;
            }

            return array_merge($status, ['job_id' => $jobId]);
        }

        return null;
    }

    private function slugCacheKey(string $entityType, string $slug, ?string $locale = null, ?string $contextTag = null): string
    {
        $parts = [
            $this->sanitizeKeyPart($entityType),
            $this->sanitizeKeyPart($slug),
        ];

        if ($locale !== null && $locale !== '') {
            $parts[] = 'loc-'.$this->sanitizeKeyPart($locale);
        }

        if ($contextTag !== null && $contextTag !== '') {
            $parts[] = 'ctx-'.$this->sanitizeKeyPart($contextTag);
        }

        return 'ai_job_slug:'.implode(':', $parts);
    }

    private function generationSlotKey(string $entityType, string $slug, ?string $locale = null, ?string $contextTag = null): string
    {
        $parts = [
            $this->sanitizeKeyPart($entityType),
            $this->sanitizeKeyPart($slug),
        ];

        if ($locale !== null && $locale !== '') {
            $parts[] = 'loc-'.$this->sanitizeKeyPart($locale);
        }

        if ($contextTag !== null && $contextTag !== '') {
            $parts[] = 'ctx-'.$this->sanitizeKeyPart($contextTag);
        }

        return 'ai_job_inflight:'.implode(':', $parts);
    }

    private function storeSlugMapping(string $entityType, string $slug, string $jobId, string $status, ?string $locale = null, ?string $contextTag = null): void
    {
        $keys = array_unique(array_filter([
            $this->slugCacheKey($entityType, $slug, $locale, $contextTag),
            $this->slugCacheKey($entityType, $slug),
        ]));

        foreach ($keys as $key) {
            if (! $this->isActiveStatus($status)) {
                Cache::forget($key);
                Log::info('JobStatusService: forget slug mapping (inactive status)', [
                    'slug_key' => $key,
                    'job_id' => $jobId,
                    'status' => $status,
                ]);

                continue;
            }

            Cache::put(
                $key,
                $jobId,
                Carbon::now()->addMinutes(self::CACHE_TTL_MINUTES)
            );
            Log::info('JobStatusService: stored slug mapping', [
                'slug_key' => $key,
                'job_id' => $jobId,
                'status' => $status,
            ]);
        }
    }

    private function isActiveStatus(string $status): bool
    {
        return in_array($status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    private function sanitizeKeyPart(string $value): string
    {
        return str_replace(':', '_', strtolower($value));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return (string) $value;
    }
}
