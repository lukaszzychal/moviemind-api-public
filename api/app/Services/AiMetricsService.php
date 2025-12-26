<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiGenerationMetric;
use Illuminate\Support\Collection;

/**
 * Service for analyzing AI generation metrics.
 */
class AiMetricsService
{
    /**
     * Get token usage statistics by format.
     */
    public function getTokenUsageByFormat(?string $entityType = null): Collection
    {
        $query = AiGenerationMetric::query();

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->selectRaw('
                data_format,
                COUNT(*) as total_requests,
                AVG(total_tokens) as avg_tokens,
                AVG(prompt_tokens) as avg_prompt_tokens,
                AVG(completion_tokens) as avg_completion_tokens,
                AVG(token_savings_vs_json) as avg_savings_percent,
                SUM(total_tokens) as total_tokens
            ')
            ->groupBy('data_format')
            ->get();
    }

    /**
     * Get parsing accuracy by format.
     */
    public function getParsingAccuracy(?string $entityType = null): Collection
    {
        $query = AiGenerationMetric::query();

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->selectRaw('
                data_format,
                COUNT(*) as total_requests,
                SUM(CASE WHEN parsing_successful = true THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN parsing_successful = false THEN 1 ELSE 0 END) as failed,
                ROUND(
                    SUM(CASE WHEN parsing_successful = true THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
                    2
                ) as accuracy_percent
            ')
            ->groupBy('data_format')
            ->get();
    }

    /**
     * Get error statistics.
     */
    public function getErrorStatistics(?string $entityType = null): Collection
    {
        $query = AiGenerationMetric::query()
            ->where('parsing_successful', false);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        return $query->selectRaw('
                data_format,
                COUNT(*) as error_count,
                COUNT(DISTINCT entity_type) as affected_entity_types,
                AVG(response_time_ms) as avg_response_time_ms
            ')
            ->groupBy('data_format')
            ->get();
    }

    /**
     * Get comparison: TOON vs JSON.
     */
    public function getFormatComparison(?string $entityType = null): array
    {
        $metrics = $this->getTokenUsageByFormat($entityType);
        $accuracy = $this->getParsingAccuracy($entityType);

        $json = $metrics->firstWhere('data_format', 'JSON');
        $toon = $metrics->firstWhere('data_format', 'TOON');

        if (! $json || ! $toon) {
            return ['error' => 'Insufficient data for comparison'];
        }

        $jsonAccuracy = $accuracy->firstWhere('data_format', 'JSON');
        $toonAccuracy = $accuracy->firstWhere('data_format', 'TOON');

        return [
            'token_savings' => [
                'absolute' => (int) ($json->total_tokens - $toon->total_tokens),
                'percent' => (float) ($toon->avg_savings_percent ?? 0),
            ],
            'accuracy' => [
                'json' => (float) ($jsonAccuracy->accuracy_percent ?? 0),
                'toon' => (float) ($toonAccuracy->accuracy_percent ?? 0),
                'difference' => (float) (($toonAccuracy->accuracy_percent ?? 0) - ($jsonAccuracy->accuracy_percent ?? 0)),
            ],
            'avg_tokens' => [
                'json' => (int) $json->avg_tokens,
                'toon' => (int) $toon->avg_tokens,
                'savings' => (int) ($json->avg_tokens - $toon->avg_tokens),
            ],
        ];
    }
}
