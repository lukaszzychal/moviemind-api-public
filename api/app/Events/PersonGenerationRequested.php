<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonGenerationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingPersonId = null,
        public ?string $baselineBioId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}
}
