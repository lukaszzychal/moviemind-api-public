<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JobStatusService;
use Illuminate\Http\JsonResponse;

class JobsController extends Controller
{
    public function __construct(private readonly JobStatusService $jobStatusService) {}

    public function show(string $id): JsonResponse
    {
        $data = $this->jobStatusService->getStatus($id);

        if (! $data) {
            return response()->json([
                'job_id' => $id,
                'status' => 'UNKNOWN',
            ], 404);
        }

        return response()->json($data);
    }
}
