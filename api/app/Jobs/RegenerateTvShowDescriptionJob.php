<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\TvShow;
use App\Models\TvShowDescription;
use App\Repositories\TvShowRepository;
use App\Services\AiOutputValidator;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for regenerating a TV show description after report verification.
 */
class RegenerateTvShowDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $tvShowId,
        public string $descriptionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        OpenAiClientInterface $openAiClient,
        TvShowRepository $tvShowRepository,
        AiOutputValidator $outputValidator
    ): void {
        Log::info('RegenerateTvShowDescriptionJob started', [
            'tv_show_id' => $this->tvShowId,
            'description_id' => $this->descriptionId,
        ]);

        $tvShow = TvShow::find($this->tvShowId);
        if ($tvShow === null) {
            Log::warning('TV show not found for regeneration', [
                'tv_show_id' => $this->tvShowId,
            ]);
            $this->fail(new \RuntimeException("TV show not found: {$this->tvShowId}"));

            return;
        }

        $description = TvShowDescription::find($this->descriptionId);
        if ($description === null) {
            Log::warning('Description not found for regeneration', [
                'description_id' => $this->descriptionId,
            ]);
            $this->fail(new \RuntimeException("Description not found: {$this->descriptionId}"));

            return;
        }

        // Generate new description using AI
        // Note: generateTvShow doesn't support context_tag/locale directly,
        // so we use the method and preserve original context_tag/locale
        $result = $openAiClient->generateTvShow($tvShow->slug, null);

        if (! $result['success'] || empty($result['description'])) {
            Log::error('Failed to generate new description', [
                'tv_show_id' => $this->tvShowId,
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
                'tv_show_id' => $this->tvShowId,
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

        // Find max version number for this (tv_show_id, locale, context_tag) combination
        $maxVersion = TvShowDescription::where('tv_show_id', $tvShow->id)
            ->where('locale', $description->locale)
            ->where('context_tag', $description->context_tag)
            ->max('version_number') ?? 0;

        // Create new description with incremented version number, preserving context_tag and locale
        $newDescription = TvShowDescription::create([
            'tv_show_id' => $tvShow->id,
            'locale' => $description->locale,
            'text' => $validation['sanitized'],
            'context_tag' => $description->context_tag,
            'origin' => $description->origin,
            'ai_model' => $result['model'] ?? 'gpt-4o-mini',
            'version_number' => $maxVersion + 1,
            'archived_at' => null,
        ]);

        // Update TV show's default_description_id to point to new description
        $newDescriptionId = $newDescription->id;
        $tvShow->update([
            'default_description_id' => $newDescriptionId,
        ]);

        // Update all related reports to RESOLVED (both old and new description IDs)
        \App\Models\TvShowReport::where('tv_show_id', $this->tvShowId)
            ->whereIn('description_id', [$this->descriptionId, $newDescriptionId])
            ->where('status', ReportStatus::VERIFIED)
            ->update([
                'status' => ReportStatus::RESOLVED,
                'resolved_at' => now(),
            ]);

        Log::info('TV show description regenerated successfully', [
            'tv_show_id' => $this->tvShowId,
            'old_description_id' => $this->descriptionId,
            'new_description_id' => $newDescriptionId,
            'version_number' => $newDescription->version_number,
        ]);
    }
}
