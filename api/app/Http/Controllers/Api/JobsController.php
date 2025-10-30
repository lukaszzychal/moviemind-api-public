<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class JobsController extends Controller
{
    public function show(string $id)
    {
        // Mock status for now
        return response()->json([
            'job_id' => $id,
            'status' => 'DONE',
        ]);
    }
}


