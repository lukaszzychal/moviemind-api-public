<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TmdbSnapshot;
use App\Models\TvSeries;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize TV series relationships from TMDb.
 * This job runs asynchronously after a TV series is created or metadata is synced.
 *
 * @author MovieMind API Team
 */
class SyncTvSeriesRelationshipsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $tvSeriesId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncTvSeriesRelationshipsJob started', [
            'tv_series_id' => $this->tvSeriesId,
            'attempt' => $this->attempts(),
        ]);

        $tvSeries = TvSeries::find($this->tvSeriesId);
        if (! $tvSeries) {
            Log::warning('SyncTvSeriesRelationshipsJob: TV series not found', [
                'tv_series_id' => $this->tvSeriesId,
            ]);

            return;
        }

        /** @var TmdbSnapshot|null $snapshot */
        $snapshot = TmdbSnapshot::where('entity_type', 'TV_SERIES')
            ->where('entity_id', $tvSeries->id)
            ->first();

        if (! $snapshot) {
            Log::warning('SyncTvSeriesRelationshipsJob: No TMDb snapshot found for TV series', [
                'tv_series_id' => $this->tvSeriesId,
            ]);

            return;
        }

        // TODO: Implement full TMDb integration for TV series relationships
        // TV series can have: similar, recommendations, and TV series with same name
        // For now, this is a placeholder that logs the sync attempt

        Log::info('SyncTvSeriesRelationshipsJob finished (placeholder)', [
            'tv_series_id' => $this->tvSeriesId,
            'tmdb_id' => $snapshot->tmdb_id,
        ]);
    }
}
