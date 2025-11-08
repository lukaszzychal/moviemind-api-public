<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer\Fixers;

use App\Support\PhpstanFixer\AutoFixMode;
use App\Support\PhpstanFixer\FixSuggestion;
use App\Support\PhpstanFixer\PhpstanIssue;

interface FixStrategy
{
    public function supports(PhpstanIssue $issue): bool;

    public function handle(PhpstanIssue $issue, AutoFixMode $mode): ?FixSuggestion;
}
