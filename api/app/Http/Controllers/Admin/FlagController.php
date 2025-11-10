<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetFlagRequest;
use App\Services\FeatureFlag\FeatureFlagManager;
use App\Services\FeatureFlag\FeatureFlagUsageScanner;

class FlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $featureFlagManager,
        private readonly FeatureFlagUsageScanner $usageScanner,
    ) {}

    public function index()
    {
        $data = collect($this->featureFlagManager->all())
            ->map(fn (array $meta, string $name) => [
                'name' => $name,
                'active' => $this->featureFlagManager->isActive($name),
                'description' => $meta['description'] ?? null,
                'category' => $meta['category'] ?? null,
                'default' => (bool) ($meta['default'] ?? false),
                'togglable' => (bool) ($meta['togglable'] ?? false),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function setFlag(SetFlagRequest $request, string $name)
    {
        $flag = $this->featureFlagManager->get($name);

        if ($flag === null) {
            abort(404, 'Feature flag not found.');
        }

        if (! $this->featureFlagManager->isTogglable($name)) {
            abort(403, 'Feature flag cannot be toggled via API.');
        }

        $this->featureFlagManager->toggle($name, $request->wantsActivation());

        return response()->json([
            'name' => $name,
            'active' => $this->featureFlagManager->isActive($name),
        ]);
    }

    public function usage()
    {
        $flagNames = array_keys($this->featureFlagManager->all());
        $usage = $this->usageScanner->scan($flagNames);

        return response()->json(['usage' => $usage]);
    }
}
