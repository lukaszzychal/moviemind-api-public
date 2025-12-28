<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\TvSeries;
use App\Models\TvSeriesDescription;
use App\Repositories\TvSeriesRepository;
use App\Services\AiOutputValidator;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for regenerating a TV series description after report verification.
 */
class RegenerateTvSeriesDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $tvSeriesId,
        public string $descriptionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        OpenAiClientInterface $openAiClient,
        TvSeriesRepository $tvSeriesRepository,
        AiOutputValidator $outputValidator
    ): void {
        Log::info('RegenerateTvSeriesDescriptionJob started', [
            'tv_series_id' => $this->tvSeriesId,
            'description_id' => $this->descriptionId,
        ]);

        $tvSeries = TvSeries::find($this->tvSeriesId);
        if ($tvSeries === null) {
            Log::warning('TV series not found for regeneration', [
                'tv_series_id' => $this->tvSeriesId,
            ]);
            $this->fail(new \RuntimeException("TV series not found: {$this->tvSeriesId}"));

            return;
        }

        $description = TvSeriesDescription::find($this->descriptionId);
        if ($description === null) {
            Log::warning('Description not found for regeneration', [
                'description_id' => $this->descriptionId,
            ]);
            $this->fail(new \RuntimeException("Description not found: {$this->descriptionId}"));

            return;
        }

        // Generate new description using AI
        // Note: generateTvSeries doesn't support context_tag/locale directly,
        // so we use the method and preserve original context_tag/locale
        $result = $openAiClient->generateTvSeries($tvSeries->slug, null);

        if (! $result['success'] || empty($result['description'])) {
            Log::error('Failed to generate new description', [
                'tv_series_id' => $this->tvSeriesId,
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
                'tv_series_id' => $this->tvSeriesId,
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

        // Find max version number for this (tv_series_id, locale, context_tag) combination
        $maxVersion = TvSeriesDescription::where('tv_series_id', $tvSeries->id)
            ->where('locale', $description->locale)
            ->where('context_tag', $description->context_tag)
            ->max('version_number') ?? 0;

        // Create new description with incremented version number, preserving context_tag and locale
        $newDescription = TvSeriesDescription::create([
            'tv_series_id' => $tvSeries->id,
            'locale' => $description->locale,
            'text' => $validation['sanitized'],
            'context_tag' => $description->context_tag,
            'origin' => $description->origin,
            'ai_model' => $result['model'] ?? 'gpt-4o-mini',
            'version_number' => $maxVersion + 1,
            'archived_at' => null,
        ]);

        // Update TV series's default_description_id to point to new description
        $newDescriptionId = $newDescription->id;
        $tvSeries->update([
            'default_description_id' => $newDescriptionId,
        ]);

        // Update all related reports to RESOLVED (both old and new description IDs)
        \App\Models\TvSeriesReport::where('tv_series_id', $this->tvSeriesId)
            ->whereIn('description_id', [$this->descriptionId, $newDescriptionId])
            ->where('status', ReportStatus::VERIFIED)
            ->update([
                'status' => ReportStatus::RESOLVED,
                'resolved_at' => now(),
            ]);

        Log::info('TV series description regenerated successfully', [
            'tv_series_id' => $this->tvSeriesId,
            'old_description_id' => $this->descriptionId,
            'new_description_id' => $newDescriptionId,
            'version_number' => $newDescription->version_number,
        ]);
    }
}
