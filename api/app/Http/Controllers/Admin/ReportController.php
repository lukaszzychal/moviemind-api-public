<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\VerifyMovieReportAction;
use App\Actions\VerifyPersonReportAction;
use App\Actions\VerifyTvSeriesReportAction;
use App\Actions\VerifyTvShowReportAction;
use App\Http\Controllers\Controller;
use App\Models\MovieReport;
use App\Models\PersonReport;
use App\Models\TvSeriesReport;
use App\Models\TvShowReport;
use App\Repositories\MovieReportRepository;
use App\Repositories\PersonReportRepository;
use App\Repositories\TvSeriesReportRepository;
use App\Repositories\TvShowReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly MovieReportRepository $movieReportRepository,
        private readonly PersonReportRepository $personReportRepository,
        private readonly TvSeriesReportRepository $tvSeriesReportRepository,
        private readonly TvShowReportRepository $tvShowReportRepository,
        private readonly VerifyMovieReportAction $verifyMovieAction,
        private readonly VerifyPersonReportAction $verifyPersonAction,
        private readonly VerifyTvSeriesReportAction $verifyTvSeriesAction,
        private readonly VerifyTvShowReportAction $verifyTvShowAction
    ) {}

    /**
     * List all reports (movies, people, TV series, and/or TV shows) with filtering and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $reportType = $request->query('type', 'all'); // 'movie', 'person', 'tv_series', 'tv_show', or 'all'
        $filters = [
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];

        $perPage = (int) $request->query('per_page', 50);

        $movieReports = collect();
        $personReports = collect();
        $tvSeriesReports = collect();
        $tvShowReports = collect();

        // Fetch movie reports
        if ($reportType === 'all' || $reportType === 'movie') {
            $movieReports = $this->movieReportRepository->getAll($filters, $perPage);
        }

        // Fetch person reports
        if ($reportType === 'all' || $reportType === 'person') {
            $personReports = $this->personReportRepository->getAll($filters, $perPage);
        }

        // Fetch TV series reports
        if ($reportType === 'all' || $reportType === 'tv_series') {
            $tvSeriesReports = $this->tvSeriesReportRepository->getAll($filters, $perPage);
        }

        // Fetch TV show reports
        if ($reportType === 'all' || $reportType === 'tv_show') {
            $tvShowReports = $this->tvShowReportRepository->getAll($filters, $perPage);
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

        // Format TV series reports
        foreach ($tvSeriesReports as $report) {
            $data->push([
                'id' => $report->id,
                'entity_type' => 'tv_series',
                'tv_series_id' => $report->tv_series_id,
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

        // Format TV show reports
        foreach ($tvShowReports as $report) {
            $data->push([
                'id' => $report->id,
                'entity_type' => 'tv_show',
                'tv_show_id' => $report->tv_show_id,
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
     * Automatically detects if it's a movie, person, TV series, or TV show report.
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

        // Try to find as TV series report
        $tvSeriesReport = TvSeriesReport::find($id);
        if ($tvSeriesReport !== null) {
            try {
                $report = $this->verifyTvSeriesAction->handle($id);

                return response()->json([
                    'id' => $report->id,
                    'entity_type' => 'tv_series',
                    'tv_series_id' => $report->tv_series_id,
                    'description_id' => $report->description_id,
                    'status' => $report->status->value,
                    'verified_at' => $report->verified_at->toIso8601String(),
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json(['error' => 'Report not found'], 404);
            }
        }

        // Try to find as TV show report
        $tvShowReport = TvShowReport::find($id);
        if ($tvShowReport !== null) {
            try {
                $report = $this->verifyTvShowAction->handle($id);

                return response()->json([
                    'id' => $report->id,
                    'entity_type' => 'tv_show',
                    'tv_show_id' => $report->tv_show_id,
                    'description_id' => $report->description_id,
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
