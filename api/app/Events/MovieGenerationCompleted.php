<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MovieGenerationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $jobId,
        public string $slug,
        public string $entityId,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?string $descriptionId = null
    ) {}
}
