<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer;

final class FixSuggestion
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $summary,
        public readonly string $preview,
        public readonly bool $applied,
    ) {}
}
