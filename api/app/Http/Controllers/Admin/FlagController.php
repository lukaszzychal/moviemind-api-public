<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Laravel\Pennant\Feature;

class FlagController extends Controller
{
    public function index()
    {
        $data = collect($this->flagDefinitions())
            ->map(fn (array $meta, string $name) => [
                'name' => $name,
                'active' => (bool) Feature::active($name),
                'description' => $meta['description'] ?? null,
                'category' => $meta['category'] ?? null,
                'default' => (bool) ($meta['default'] ?? false),
                'togglable' => (bool) ($meta['togglable'] ?? false),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function setFlag(Request $request, string $name)
    {
        $request->validate([
            'state' => 'required|in:on,off',
        ]);

        $meta = $this->flagConfig($name);

        if ($meta === null) {
            abort(404, 'Feature flag not found.');
        }

        if (! ($meta['togglable'] ?? false)) {
            abort(403, 'Feature flag cannot be toggled via API.');
        }

        if ($request->input('state') === 'on') {
            Feature::activate($name);
        } else {
            Feature::deactivate($name);
        }

        return response()->json([
            'name' => $name,
            'active' => (bool) Feature::active($name),
        ]);
    }

    public function usage()
    {
        $regexes = [
            ['type' => 'active', 'pattern' => "/Feature::active\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
            ['type' => 'inactive', 'pattern' => "/Feature::inactive\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
            ['type' => 'scoped', 'pattern' => "/Feature::for\\([^)]*\\)->(?:activate|deactivate)\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
        ];

        $extractName = function (string $snippet): ?string {
            if (preg_match("/Feature::(?:active|inactive)\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/", $snippet, $m)) {
                return $m[1] ?? null; // @phpstan-ignore-line
            }
            if (preg_match("/Feature::for\\([^)]*\\)->(?:activate|deactivate)\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/", $snippet, $m)) {
                return $m[1] ?? null; // @phpstan-ignore-line
            }

            return null;
        };

        $knownFlags = collect($this->flagDefinitions())->keys()->all();
        $usage = [];
        $appPath = base_path('app');
        $files = File::allFiles($appPath);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = File::get($file->getRealPath());
            foreach ($regexes as $rx) {
                if (preg_match_all($rx['pattern'], $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        [$snippet, $offset] = $match;
                        $name = $extractName($snippet);

                        if ($name === null || ! in_array($name, $knownFlags, true)) {
                            continue;
                        }

                        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                        $usage[] = [
                            'file' => str_replace(base_path().'/', '', $file->getRealPath()),
                            'line' => $line,
                            'pattern' => $rx['type'],
                            'name' => $name,
                        ];
                    }
                }
            }
        }

        return response()->json(['usage' => $usage]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function flagDefinitions(): array
    {
        return config('pennant.flags', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function flagConfig(string $name): ?array
    {
        return $this->flagDefinitions()[$name] ?? null;
    }
}
