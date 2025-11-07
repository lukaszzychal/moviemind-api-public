<?php

namespace App\Actions;

use App\Events\PersonGenerationRequested;
use App\Services\JobStatusService;
use Illuminate\Support\Str;

class QueuePersonGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(string $slug, ?float $confidence = null): array
    {
        $jobId = (string) Str::uuid();

        $this->jobStatusService->initializeStatus(
            $jobId,
            'PERSON',
            $slug,
            $confidence
        );

        event(new PersonGenerationRequested($slug, $jobId));

        return [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for person by slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->confidenceLabel($confidence),
        ];
    }

    private function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return 'unknown';
        }

        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }
}
