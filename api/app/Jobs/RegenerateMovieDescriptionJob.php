<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
use App\Services\AiOutputValidator;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for regenerating a movie description after report verification.
 */
class RegenerateMovieDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $movieId,
        public string $descriptionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        OpenAiClientInterface $openAiClient,
        MovieRepository $movieRepository,
        AiOutputValidator $outputValidator
    ): void {
        Log::info('RegenerateMovieDescriptionJob started', [
            'movie_id' => $this->movieId,
            'description_id' => $this->descriptionId,
        ]);

        $movie = Movie::find($this->movieId);
        if ($movie === null) {
            Log::warning('Movie not found for regeneration', [
                'movie_id' => $this->movieId,
            ]);
            $this->fail(new \RuntimeException("Movie not found: {$this->movieId}"));

            return;
        }

        $description = MovieDescription::find($this->descriptionId);
        if ($description === null) {
            Log::warning('Description not found for regeneration', [
                'description_id' => $this->descriptionId,
            ]);
            $this->fail(new \RuntimeException("Description not found: {$this->descriptionId}"));

            return;
        }

        // Generate new description using AI
        $contextTag = ($description->context_tag !== null) ? $description->context_tag->value : 'DEFAULT';
        $result = $openAiClient->generateMovieDescription(
            $movie->title,
            $movie->release_year ?? 0,
            $movie->director ?? '',
            $contextTag,
            $description->locale->value,
            null // No TMDb data for regeneration
        );

        if (! $result['success'] || empty($result['description'])) {
            Log::error('Failed to generate new description', [
                'movie_id' => $this->movieId,
                'description_id' => $this->descriptionId,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            $this->fail(new \RuntimeException('Failed to generate new description'));

            return;
        }

        // Validate and sanitize output
        $validation = $outputValidator->validateAndSanitizeDescription($result['description']);

        if (! $validation['valid']) {
            Log::error('Generated description failed validation', [
                'movie_id' => $this->movieId,
                'description_id' => $this->descriptionId,
                'errors' => $validation['errors'],
            ]);
            $this->fail(new \RuntimeException('Generated description failed validation: '.implode(', ', $validation['errors'])));

            return;
        }

        // Archive old description (versioning)
        $description->update([
            'archived_at' => now(),
        ]);

        // Find max version number for this (movie_id, locale, context_tag) combination
        $maxVersion = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', $description->locale)
            ->where('context_tag', $description->context_tag)
            ->max('version_number') ?? 0;

        // Create new description with incremented version number
        $newDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => $description->locale,
            'text' => $validation['sanitized'],
            'context_tag' => $description->context_tag,
            'origin' => $description->origin,
            'ai_model' => $result['model'] ?? 'gpt-4o-mini',
            'version_number' => $maxVersion + 1,
            'archived_at' => null,
        ]);

        // Update movie's default_description_id to point to new description
        $newDescriptionId = $newDescription->id;
        $movie->update([
            'default_description_id' => $newDescriptionId,
        ]);

        // Update all related reports to RESOLVED (both old and new description IDs)
        \App\Models\MovieReport::where('movie_id', $this->movieId)
            ->whereIn('description_id', [$this->descriptionId, $newDescriptionId])
            ->where('status', ReportStatus::VERIFIED)
            ->update([
                'status' => ReportStatus::RESOLVED,
                'resolved_at' => now(),
            ]);

        Log::info('Movie description regenerated successfully', [
            'movie_id' => $this->movieId,
            'old_description_id' => $this->descriptionId,
            'new_description_id' => $newDescriptionId,
            'version_number' => $newDescription->version_number,
        ]);
    }
}
