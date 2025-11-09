<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAiClientInterface;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly OpenAiClientInterface $openAiClient
    ) {}

    public function openAi(): JsonResponse
    {
        $result = $this->openAiClient->health();

        $success = (bool) $result['success'];
        $status = 200;

        if (! $success) {
            $status = array_key_exists('status', $result) ? (int) $result['status'] : 503;
        }

        return response()->json($result, $status);
    }
}
