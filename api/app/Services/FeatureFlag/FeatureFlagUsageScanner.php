<?php

declare(strict_types=1);

namespace App\Services\FeatureFlag;

use Illuminate\Support\Facades\File;

class FeatureFlagUsageScanner
{
    /**
     * @var array<string, string>
     */
    private const PATTERNS = [
        'active' => "/Feature::active\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/",
        'inactive' => "/Feature::inactive\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/",
        'scoped' => "/Feature::for\\([^)]*\\)->(?:activate|deactivate)\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/",
    ];

    /**
     * @param  string[]  $flagNames
     * @return array<int, array<string, int|string>>
     */
    public function scan(array $flagNames): array
    {
        $knownFlags = array_flip($flagNames);
        $usage = [];
        $appPath = base_path('app');

        foreach (File::allFiles($appPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = File::get($file->getRealPath());

            foreach (self::PATTERNS as $type => $pattern) {
                if (! preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($matches[0] as $index => $match) {
                    [, $offset] = $match;
                    $name = $matches[1][$index][0] ?? null;

                    if ($name === null || ! isset($knownFlags[$name])) {
                        continue;
                    }

                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;

                    $usage[] = [
                        'file' => str_replace(base_path().'/', '', $file->getRealPath()),
                        'line' => $line,
                        'pattern' => $type,
                        'name' => $name,
                    ];
                }
            }
        }

        return $usage;
    }
}
