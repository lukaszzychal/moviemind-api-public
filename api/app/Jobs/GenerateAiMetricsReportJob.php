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

            // Build report
            $report = [
                'period' => $this->period,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'generated_at' => now()->toIso8601String(),
                'token_usage' => $tokenUsage->toArray(),
                'parsing_accuracy' => $parsingAccuracy->toArray(),
                'error_statistics' => $errorStatistics->toArray(),
                'comparison' => $comparison,
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
}
