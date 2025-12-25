<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}

    /**
     * List all API keys.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiKey::query()->orderBy('created_at', 'desc');

        // Filter by active status
        if ($request->has('active')) {
            $isActive = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Filter by plan
        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        $apiKeys = $query->get();

        $data = $apiKeys->map(function (ApiKey $apiKey) {
            return [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'key_prefix' => $apiKey->key_prefix,
                'plan_id' => $apiKey->plan_id,
                'user_id' => $apiKey->user_id,
                'is_active' => $apiKey->is_active,
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'expires_at' => $apiKey->expires_at?->toIso8601String(),
                'created_at' => $apiKey->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /**
     * Create a new API key.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'uuid'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $result = $this->apiKeyService->createKey(
            name: $validated['name'],
            planId: $validated['plan_id'] ?? null,
            userId: $validated['user_id'] ?? null,
            expiresAt: isset($validated['expires_at']) && $validated['expires_at'] ? new \DateTime($validated['expires_at']) : null
        );

        // Return the plaintext key only on creation (user should save it immediately)
        $responseData = [
            'id' => $result['apiKey']->id,
            'name' => $result['apiKey']->name,
            'key' => $result['key'], // Plaintext key - shown only once!
            'key_prefix' => $result['apiKey']->key_prefix,
            'plan_id' => $result['apiKey']->plan_id,
            'is_active' => $result['apiKey']->is_active,
            'created_at' => $result['apiKey']->created_at->toIso8601String(),
            'warning' => 'Save this key immediately. You will not be able to see it again.',
        ];

        // Include expires_at only if it's not null
        if ($result['apiKey']->expires_at !== null) {
            $responseData['expires_at'] = $result['apiKey']->expires_at->toIso8601String();
        }

        return response()->json($responseData, 201);
    }

    /**
     * Revoke (deactivate) an API key.
     */
    public function revoke(string $id): JsonResponse
    {
        $apiKey = ApiKey::find($id);

        if ($apiKey === null) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'API key not found.',
            ], 404);
        }

        $apiKey->update(['is_active' => false]);

        return response()->json([
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'is_active' => false,
            'message' => 'API key revoked successfully.',
        ]);
    }

    /**
     * Regenerate an API key (revoke old, create new).
     */
    public function regenerate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $oldApiKey = ApiKey::find($id);

        if ($oldApiKey === null) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'API key not found.',
            ], 404);
        }

        // Revoke old key
        $oldApiKey->update(['is_active' => false]);

        // Create new key with same plan/user (or update name if provided)
        $result = $this->apiKeyService->createKey(
            name: $validated['name'] ?? $oldApiKey->name,
            planId: $oldApiKey->plan_id,
            userId: $oldApiKey->user_id,
            expiresAt: $oldApiKey->expires_at
        );

        $responseData = [
            'id' => $result['apiKey']->id,
            'name' => $result['apiKey']->name,
            'key' => $result['key'], // Plaintext key - shown only once!
            'key_prefix' => $result['apiKey']->key_prefix,
            'plan_id' => $result['apiKey']->plan_id,
            'is_active' => $result['apiKey']->is_active,
            'created_at' => $result['apiKey']->created_at->toIso8601String(),
            'revoked_key_id' => $oldApiKey->id,
            'warning' => 'Save this key immediately. You will not be able to see it again.',
        ];

        // Include expires_at only if it's not null
        if ($result['apiKey']->expires_at !== null) {
            $responseData['expires_at'] = $result['apiKey']->expires_at->toIso8601String();
        }

        return response()->json($responseData, 201);
    }
}
