<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\ApplicationFeedback;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    /**
     * Submit anonymous feedback (no personal data).
     */
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $feedback = ApplicationFeedback::create([
            'message' => $validated['message'],
            'category' => $validated['category'] ?? null,
            'status' => ApplicationFeedback::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Feedback received. Thank you.',
            'id' => $feedback->id,
        ], 201);
    }
}
