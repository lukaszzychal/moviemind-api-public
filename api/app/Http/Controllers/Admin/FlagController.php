<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Laravel\Pennant\Feature;

class FlagController extends Controller
{
    public function index()
    {
        $features = $this->listFeatureClasses();
        $data = [];
        foreach ($features as $flag) {
            $data[] = [
                'name' => $flag,
                'active' => (bool) Feature::active($flag),
            ];
        }
        return response()->json(['data' => $data]);
    }

    public function setFlag(Request $request, string $name)
    {
        $request->validate([
            'state' => 'required|in:on,off',
        ]);
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
        $patterns = [
            "Feature::active('" => 'active',
            'Feature::inactive(' => 'inactive',
            'Feature::for(' => 'scoped',
        ];
        $usage = [];
        $appPath = base_path('app');
        $files = File::allFiles($appPath);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = File::get($file->getRealPath());
            foreach ($patterns as $needle => $type) {
                $pos = 0;
                while (($pos = strpos($contents, $needle, $pos)) !== false) {
                    $line = substr_count(substr($contents, 0, $pos), "\n") + 1;
                    $usage[] = [
                        'file' => str_replace(base_path() . '/', '', $file->getRealPath()),
                        'line' => $line,
                        'pattern' => $type,
                    ];
                    $pos += strlen($needle);
                }
            }
        }
        return response()->json(['usage' => $usage]);
    }

    private function listFeatureClasses(): array
    {
        $dir = app_path('Features');
        if (!File::exists($dir)) return [];
        $flags = [];
        foreach (File::files($dir) as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $flags[] = $name;
        }
        return $flags;
    }
}
