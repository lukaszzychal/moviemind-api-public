<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TvShowGenerationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingTvShowId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}
}
