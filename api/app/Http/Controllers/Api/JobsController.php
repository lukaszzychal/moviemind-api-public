<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class JobsController extends Controller
{
    public function show(string $id)
    {
        $data = Cache::get($this->cacheKey($id));
        if (! $data) {
            return response()->json([
                'job_id' => $id,
                'status' => 'UNKNOWN',
            ], 404);
        }

        return response()->json($data);
    }

    private function cacheKey(string $jobId): string
    {
        return "ai_job:" . $jobId;
    }
}


