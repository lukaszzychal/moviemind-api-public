<?php

namespace App\Actions;

use App\Events\MovieGenerationRequested;
use App\Models\Movie;
use App\Services\JobStatusService;
use Illuminate\Support\Str;

class QueueMovieGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(string $slug, ?float $confidence = null, ?Movie $existingMovie = null): array
    {
        $jobId = (string) Str::uuid();
        $existingMovie ??= Movie::where('slug', $slug)->first();
        $baselineDescriptionId = $existingMovie?->default_description_id;

        $this->jobStatusService->initializeStatus(
            $jobId,
            'MOVIE',
            $slug,
            $confidence
        );

        event(new MovieGenerationRequested(
            $slug,
            $jobId,
            existingMovieId: $existingMovie?->id,
            baselineDescriptionId: $baselineDescriptionId
        ));

        $response = [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->confidenceLabel($confidence),
        ];

        if ($existingMovie) {
            $response['existing_id'] = $existingMovie->id;
            $response['description_id'] = $baselineDescriptionId;
        }

        return $response;
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
