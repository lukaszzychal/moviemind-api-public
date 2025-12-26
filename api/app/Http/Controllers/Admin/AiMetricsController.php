<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiMetricsController extends Controller
{
    public function __construct(
        private readonly AiMetricsService $metricsService
    ) {}

    /**
     * Get token usage statistics.
     */
    public function tokenUsage(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getTokenUsageByFormat($entityType);

        return response()->json([
            'data' => $stats,
            'summary' => [
                'total_requests' => $stats->sum('total_requests'),
                'total_tokens' => $stats->sum('total_tokens'),
            ],
        ]);
    }

    /**
     * Get parsing accuracy statistics.
     */
    public function parsingAccuracy(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getParsingAccuracy($entityType);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get error statistics.
     */
    public function errorStatistics(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $stats = $this->metricsService->getErrorStatistics($entityType);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get format comparison (TOON vs JSON).
     */
    public function formatComparison(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $comparison = $this->metricsService->getFormatComparison($entityType);
        $parsingAccuracy = $this->metricsService->getParsingAccuracy($entityType);
        $errorStatistics = $this->metricsService->getErrorStatistics($entityType);

        // Generate recommendation
        $recommendation = $this->generateRecommendation($comparison, $parsingAccuracy, $errorStatistics);

        return response()->json([
            'data' => $comparison,
            'recommendation' => $recommendation,
        ]);
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
