<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\VerifyMovieReportAction;
use App\Actions\VerifyPersonReportAction;
use App\Http\Controllers\Controller;
use App\Models\MovieReport;
use App\Models\PersonReport;
use App\Repositories\MovieReportRepository;
use App\Repositories\PersonReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly MovieReportRepository $movieReportRepository,
        private readonly PersonReportRepository $personReportRepository,
        private readonly VerifyMovieReportAction $verifyMovieAction,
        private readonly VerifyPersonReportAction $verifyPersonAction
    ) {}

    /**
     * List all reports (movies and/or people) with filtering and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $reportType = $request->query('type', 'all'); // 'movie', 'person', or 'all'
        $filters = [
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];

        $perPage = (int) $request->query('per_page', 50);

        $movieReports = collect();
        $personReports = collect();

        // Fetch movie reports
        if ($reportType === 'all' || $reportType === 'movie') {
            $movieReports = $this->movieReportRepository->getAll($filters, $perPage);
        }

        // Fetch person reports
        if ($reportType === 'all' || $reportType === 'person') {
            $personReports = $this->personReportRepository->getAll($filters, $perPage);
        }

        // Combine and format data
        $data = collect();

        // Format movie reports
        foreach ($movieReports as $report) {
            $data->push([
                'id' => $report->id,
                'entity_type' => 'movie',
                'movie_id' => $report->movie_id,
                'description_id' => $report->description_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'suggested_fix' => $report->suggested_fix,
                'status' => $report->status->value,
                'priority_score' => (float) $report->priority_score,
                'verified_by' => $report->verified_by,
                'verified_at' => $report->verified_at?->toIso8601String(),
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'created_at' => $report->created_at->toIso8601String(),
            ]);
        }

        // Format person reports
        foreach ($personReports as $report) {
            $data->push([
                'id' => $report->id,
                'entity_type' => 'person',
                'person_id' => $report->person_id,
                'bio_id' => $report->bio_id,
                'type' => $report->type->value,
                'message' => $report->message,
                'suggested_fix' => $report->suggested_fix,
                'status' => $report->status->value,
                'priority_score' => (float) $report->priority_score,
                'verified_by' => $report->verified_by,
                'verified_at' => $report->verified_at?->toIso8601String(),
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'created_at' => $report->created_at->toIso8601String(),
            ]);
        }

        // Sort by priority_score desc, then created_at desc
        $data = $data->sortByDesc(function ($report) {
            return [$report['priority_score'], $report['created_at']];
        })->values();

        // Simple pagination (manual, since we're combining two sources)
        $total = $data->count();
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);
        $offset = ($page - 1) * $perPage;
        $paginatedData = $data->slice($offset, $perPage)->values();

        return response()->json([
            'data' => $paginatedData,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Verify a report and trigger regeneration.
     * Automatically detects if it's a movie or person report.
     */
    public function verify(string $id): JsonResponse
    {
        // Try to find as movie report first
        $movieReport = MovieReport::find($id);
        if ($movieReport !== null) {
            try {
                $report = $this->verifyMovieAction->handle($id);

                return response()->json([
                    'id' => $report->id,
                    'entity_type' => 'movie',
                    'movie_id' => $report->movie_id,
                    'description_id' => $report->description_id,
                    'status' => $report->status->value,
                    'verified_at' => $report->verified_at->toIso8601String(),
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json(['error' => 'Report not found'], 404);
            }
        }

        // Try to find as person report
        $personReport = PersonReport::find($id);
        if ($personReport !== null) {
            try {
                $report = $this->verifyPersonAction->handle($id);

                return response()->json([
                    'id' => $report->id,
                    'entity_type' => 'person',
                    'person_id' => $report->person_id,
                    'bio_id' => $report->bio_id,
                    'status' => $report->status->value,
                    'verified_at' => $report->verified_at->toIso8601String(),
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json(['error' => 'Report not found'], 404);
            }
        }

        return response()->json(['error' => 'Report not found'], 404);
    }
}
