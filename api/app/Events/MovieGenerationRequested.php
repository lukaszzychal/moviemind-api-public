<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MovieGenerationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?int $existingMovieId = null,
        public ?int $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}
}
