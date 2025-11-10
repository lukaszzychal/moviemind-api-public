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
        public ?int $existingPersonId = null,
        public ?int $baselineBioId = null,
        public ?string $locale = null,
        public ?string $contextTag = null
    ) {}
}
