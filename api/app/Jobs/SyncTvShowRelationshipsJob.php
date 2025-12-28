<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TmdbSnapshot;
use App\Models\TvShow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize TV show relationships from TMDb.
 * This job runs asynchronously after a TV show is created or metadata is synced.
 *
 * @author MovieMind API Team
 */
class SyncTvShowRelationshipsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $tvShowId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SyncTvShowRelationshipsJob started', [
            'tv_show_id' => $this->tvShowId,
            'attempt' => $this->attempts(),
        ]);

        $tvShow = TvShow::find($this->tvShowId);
        if (! $tvShow) {
            Log::warning('SyncTvShowRelationshipsJob: TV show not found', [
                'tv_show_id' => $this->tvShowId,
            ]);

            return;
        }

        /** @var TmdbSnapshot|null $snapshot */
        $snapshot = TmdbSnapshot::where('entity_type', 'TV_SHOW')
            ->where('entity_id', $tvShow->id)
            ->first();

        if (! $snapshot) {
            Log::warning('SyncTvShowRelationshipsJob: No TMDb snapshot found for TV show', [
                'tv_show_id' => $this->tvShowId,
            ]);

            return;
        }

        // TODO: Implement full TMDb integration for TV show relationships
        // TV shows can have: similar, recommendations
        // For now, this is a placeholder that logs the sync attempt

        Log::info('SyncTvShowRelationshipsJob finished (placeholder)', [
            'tv_show_id' => $this->tvShowId,
            'tmdb_id' => $snapshot->tmdb_id,
        ]);
    }
}
