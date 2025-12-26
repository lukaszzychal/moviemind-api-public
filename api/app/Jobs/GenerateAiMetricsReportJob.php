<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AiMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job to generate AI metrics reports periodically.
 *
 * This job aggregates AI generation metrics and generates reports
 * that can be used for analysis and decision-making.
 */
class GenerateAiMetricsReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $period = 'daily' // daily, weekly, monthly
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AiMetricsService $metricsService): void
    {
        try {
            $startDate = $this->getStartDate();
            $endDate = now();

            Log::info("GenerateAiMetricsReportJob: Generating {$this->period} report", [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ]);

            // Get metrics data
            $tokenUsage = $metricsService->getTokenUsageByFormat();
            $parsingAccuracy = $metricsService->getParsingAccuracy();
            $errorStatistics = $metricsService->getErrorStatistics();
            $comparison = $metricsService->getFormatComparison();

            // Build report with recommendation
            $recommendation = $this->generateRecommendation($comparison, $parsingAccuracy, $errorStatistics);

            $report = [
                'period' => $this->period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'generated_at' => now()->toIso8601String(),
                'token_usage' => $tokenUsage->toArray(),
                'parsing_accuracy' => $parsingAccuracy->toArray(),
                'error_statistics' => $errorStatistics->toArray(),
                'comparison' => $comparison,
                'recommendation' => $recommendation,
                'summary' => [
                    'total_requests' => $tokenUsage->sum('total_requests'),
                    'total_tokens' => $tokenUsage->sum('total_tokens'),
                    'avg_accuracy' => $parsingAccuracy->avg('accuracy_percent'),
                ],
            ];

            // Save report to storage
            $filename = $this->generateFilename();
            $path = "reports/ai-metrics/{$filename}";
            Storage::put($path, json_encode($report, JSON_PRETTY_PRINT));

            Log::info('GenerateAiMetricsReportJob: Report generated successfully', [
                'path' => $path,
                'period' => $this->period,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateAiMetricsReportJob: Failed to generate report', [
                'period' => $this->period,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(): \Carbon\Carbon
    {
        return match ($this->period) {
            'daily' => now()->subDay(),
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    /**
     * Generate filename for the report.
     */
    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "ai-metrics-{$this->period}-{$timestamp}.json";
    }

    /**
     * Generate recommendation based on comparison data.
     */
    private function generateRecommendation(array $comparison, $parsingAccuracy, $errorStatistics): array
    {
        // If no comparison data, return neutral recommendation
        if (isset($comparison['error'])) {
            return [
                'decision' => 'INSUFFICIENT_DATA',
                'message' => 'Not enough data for both formats. Need data for JSON and TOON.',
                'reason' => 'comparison_not_possible',
            ];
        }

        $tokenSavingsPercent = $comparison['token_savings']['percent'] ?? 0;
        $toonAccuracy = $comparison['accuracy']['toon'] ?? 0;
        $accuracyDifference = abs($comparison['accuracy']['difference'] ?? 0);

        // Get error rates
        $jsonAccuracy = $parsingAccuracy->firstWhere('data_format', 'JSON');
        $toonAccuracyData = $parsingAccuracy->firstWhere('data_format', 'TOON');
        $jsonErrors = $errorStatistics->firstWhere('data_format', 'JSON');
        $toonErrors = $errorStatistics->firstWhere('data_format', 'TOON');
        $jsonErrorRate = $jsonErrors && $jsonAccuracy ? ($jsonErrors->error_count / max($jsonAccuracy->total_requests, 1)) * 100 : 0;
        $toonErrorRate = $toonErrors && $toonAccuracyData ? ($toonErrors->error_count / max($toonAccuracyData->total_requests, 1)) * 100 : 0;

        // Decision logic
        $useToon = $tokenSavingsPercent >= 20
            && $toonAccuracy >= 95
            && $accuracyDifference <= 3
            && $toonErrorRate <= 5;

        $considerToon = $tokenSavingsPercent >= 15
            && $toonAccuracy >= 92
            && $accuracyDifference <= 5
            && $toonErrorRate <= 10;

        if ($useToon) {
            return [
                'decision' => 'USE_TOON',
                'message' => 'TOON is recommended. Significant token savings with comparable accuracy.',
                'reason' => 'high_savings_high_accuracy',
                'details' => [
                    'token_savings_percent' => round($tokenSavingsPercent, 2),
                    'toon_accuracy' => round($toonAccuracy, 2),
                    'accuracy_difference' => round($accuracyDifference, 2),
                    'toon_error_rate' => round($toonErrorRate, 2),
                ],
            ];
        }

        if ($considerToon) {
            return [
                'decision' => 'CONSIDER_TOON',
                'message' => 'TOON may be beneficial, but requires improvements in accuracy or error handling.',
                'reason' => 'moderate_savings_moderate_accuracy',
                'details' => [
                    'token_savings_percent' => round($tokenSavingsPercent, 2),
                    'toon_accuracy' => round($toonAccuracy, 2),
                    'accuracy_difference' => round($accuracyDifference, 2),
                    'toon_error_rate' => round($toonErrorRate, 2),
                    'suggestions' => $this->getImprovementSuggestions($toonAccuracy, $accuracyDifference, $toonErrorRate),
                ],
            ];
        }

        return [
            'decision' => 'KEEP_JSON',
            'message' => 'JSON is recommended. TOON does not provide sufficient benefits.',
            'reason' => 'low_savings_or_low_accuracy',
            'details' => [
                'token_savings_percent' => round($tokenSavingsPercent, 2),
                'toon_accuracy' => round($toonAccuracy, 2),
                'accuracy_difference' => round($accuracyDifference, 2),
                'toon_error_rate' => round($toonErrorRate, 2),
            ],
        ];
    }

    /**
     * Get improvement suggestions for TOON.
     */
    private function getImprovementSuggestions(float $toonAccuracy, float $accuracyDifference, float $toonErrorRate): array
    {
        $suggestions = [];

        if ($toonAccuracy < 95) {
            $suggestions[] = 'Improve TOON prompts to increase parsing accuracy';
        }

        if ($accuracyDifference > 3) {
            $suggestions[] = 'Review TOON schema validation rules';
        }

        if ($toonErrorRate > 5) {
            $suggestions[] = 'Investigate and fix TOON parsing errors';
        }

        return $suggestions;
    }
}
