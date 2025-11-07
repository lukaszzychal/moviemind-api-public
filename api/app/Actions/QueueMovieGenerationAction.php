<?php

namespace App\Actions;

use App\Events\MovieGenerationRequested;
use App\Services\JobStatusService;
use Illuminate\Support\Str;

class QueueMovieGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(string $slug, ?float $confidence = null): array
    {
        $jobId = (string) Str::uuid();

        $this->jobStatusService->initializeStatus(
            $jobId,
            'MOVIE',
            $slug,
            $confidence
        );

        event(new MovieGenerationRequested($slug, $jobId));

        return [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
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
