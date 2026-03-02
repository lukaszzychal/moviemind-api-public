<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateFeedbackRequest;
use App\Models\ApplicationFeedback;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function index(): JsonResponse
    {
        $items = ApplicationFeedback::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($items);
    }

    public function show(string $id): JsonResponse
    {
        $feedback = ApplicationFeedback::find($id);

        if (! $feedback) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($feedback);
    }

    public function update(UpdateFeedbackRequest $request, string $id): JsonResponse
    {
        $feedback = ApplicationFeedback::find($id);

        if (! $feedback) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $feedback->update($request->validated());

        return response()->json($feedback);
    }

    public function destroy(string $id): JsonResponse
    {
        $feedback = ApplicationFeedback::find($id);

        if (! $feedback) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $feedback->delete();

        return response()->json(null, 204);
    }
}
