<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetFlagRequest;
use App\Services\FeatureFlag\FeatureFlagManager;
use App\Services\FeatureFlag\FeatureFlagUsageScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $featureFlagManager,
        private readonly FeatureFlagUsageScanner $usageScanner
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->featureFlagManager->all());
    }

    public function setFlag(SetFlagRequest $request, string $name): JsonResponse
    {
        $validated = $request->validated();
        $state = $validated['state'];

        $this->featureFlagManager->set($name, $state === 'on');

        return response()->json([
            'message' => "Feature flag '{$name}' updated.",
            'state' => $state,
        ]);
    }

    public function resetFlag(string $name): JsonResponse
    {
        $this->featureFlagManager->reset($name);

        return response()->json([
            'message' => "Feature flag '{$name}' has been reset to its default value.",
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $flagName = $request->query('flag');
        if (! $flagName) {
            return response()->json(['error' => 'Flag name is required.'], 400);
        }

        $usages = $this->usageScanner->scan($flagName);

        return response()->json([
            'flag' => $flagName,
            'usages' => $usages,
        ]);
    }
}
