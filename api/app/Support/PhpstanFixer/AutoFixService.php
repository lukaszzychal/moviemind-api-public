<?php

declare(strict_types=1);

namespace App\Support\PhpstanFixer;

use App\Support\PhpstanFixer\Fixers\FixStrategy;

final class AutoFixService
{
    /**
     * @param  iterable<FixStrategy>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
    ) {}

    /**
     * @param  iterable<PhpstanIssue>  $issues
     * @return FixSuggestion[]
     */
    public function process(iterable $issues, AutoFixMode $mode): array
    {
        $suggestions = [];

        foreach ($issues as $issue) {
            foreach ($this->strategies as $strategy) {
                if (! $strategy->supports($issue)) {
                    continue;
                }

                $suggestion = $strategy->handle($issue, $mode);

                if ($suggestion !== null) {
                    $suggestions[] = $suggestion;
                }

                break;
            }
        }

        return $suggestions;
    }
}
