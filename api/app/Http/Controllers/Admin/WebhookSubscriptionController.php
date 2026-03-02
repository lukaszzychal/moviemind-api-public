<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebhookSubscriptionRequest;
use App\Http\Requests\Admin\UpdateWebhookSubscriptionRequest;
use App\Services\WebhookSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class WebhookSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebhookSubscriptionService $service
    ) {}

    /**
     * List all registered webhook URLs (env + subscriptions) with source.
     */
    public function index(): JsonResponse
    {
        $list = $this->service->listRegistered();

        return response()->json(['data' => $list]);
    }

    /**
     * Add a webhook subscription.
     */
    public function store(StoreWebhookSubscriptionRequest $request): JsonResponse
    {
        try {
            $sub = $this->service->addSubscription(
                $request->validated('event_type'),
                $request->validated('url')
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'id' => $sub->id,
            'event_type' => $sub->event_type,
            'url' => $sub->url,
            'created_at' => $sub->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Update a webhook subscription.
     */
    public function update(UpdateWebhookSubscriptionRequest $request, string $id): JsonResponse
    {
        try {
            $sub = $this->service->updateSubscription(
                $id,
                $request->validated('event_type'),
                $request->validated('url')
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Webhook subscription not found.'], 404);
        }

        return response()->json([
            'id' => $sub->id,
            'event_type' => $sub->event_type,
            'url' => $sub->url,
            'updated_at' => $sub->updated_at->toIso8601String(),
        ]);
    }

    /**
     * Delete a webhook subscription.
     */
    public function destroy(string $id): JsonResponse|Response
    {
        try {
            $this->service->deleteSubscription($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Webhook subscription not found.'], 404);
        }

        return response()->noContent();
    }
}
