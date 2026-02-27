<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetFlagRequest;
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
        return response()->json([
            'data' => $this->featureFlagManager->allWithStatus(),
            'meta' => [
                'scope_note' => 'Only global (default) scope is effective. User scope is not implemented in API or application logic.',
            ],
        ]);
    }

    public function overrides(): JsonResponse
    {
        return response()->json([
            'data' => \App\Models\FeatureFlag::all(),
        ]);
    }

    public function setFlag(SetFlagRequest $request, string $name): JsonResponse
    {
        $validated = $request->validated();
        $state = $validated['state'];

        $this->featureFlagManager->set($name, $state === 'on');

        return response()->json([
            'name' => $name,
            'active' => $this->featureFlagManager->isActive($name),
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

        if ($flagName) {
            $flagsToScan = [$flagName];
        } else {
            // If no flag specified, scan all known flags
            $flagsToScan = array_keys($this->featureFlagManager->all());
        }

        $usages = $this->usageScanner->scan($flagsToScan);

        return response()->json([
            'flag' => $flagName ?? 'all',
            'usages' => $usages,
        ]);
    }
}
