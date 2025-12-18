<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\VerifyMovieReportAction;
use App\Http\Controllers\Controller;
use App\Models\MovieReport;
use App\Repositories\MovieReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly MovieReportRepository $reportRepository,
        private readonly VerifyMovieReportAction $verifyAction
    ) {}

    /**
     * List all movie reports with filtering and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
        ];

        $perPage = (int) $request->query('per_page', 50);
        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, MovieReport>|\Illuminate\Database\Eloquent\Collection<int, MovieReport> $reports */
        $reports = $this->reportRepository->getAll($filters, $perPage);

        $data = $reports->map(function (MovieReport $report) {
            return [
                'id' => $report->id,
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
            ];
        });

        if ($reports instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                    'last_page' => $reports->lastPage(),
                ],
            ]);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Verify a report and trigger regeneration.
     */
    public function verify(string $id): JsonResponse
    {
        try {
            $report = $this->verifyAction->handle($id);

            return response()->json([
                'id' => $report->id,
                'movie_id' => $report->movie_id,
                'description_id' => $report->description_id,
                'status' => $report->status->value,
                'verified_at' => $report->verified_at->toIso8601String(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Report not found'], 404);
        }
    }
}
