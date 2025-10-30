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
        $descriptions = $this->descriptions();
        $data = [];
        foreach ($features as $flag) {
            $data[] = [
                'name' => $flag,
                'active' => (bool) Feature::active($flag),
                'description' => $descriptions[$flag] ?? null,
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
        // Regex patterns to extract flag names and usage type
        $regexes = [
            ['type' => 'active', 'pattern' => "/Feature::active\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
            ['type' => 'inactive', 'pattern' => "/Feature::inactive\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
            ['type' => 'scoped', 'pattern' => "/Feature::for\\([^)]*\\)->(?:activate|deactivate)\\(\\s*['\"][A-Za-z0-9_]+['\"]\\s*\\)/"],
        ];

        $extractName = function (string $snippet): ?string {
            if (preg_match("/Feature::(?:active|inactive)\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/", $snippet, $m)) {
                return $m[1] ?? null;
            }
            if (preg_match("/Feature::for\\([^)]*\\)->(?:activate|deactivate)\\(\\s*['\"]([A-Za-z0-9_]+)['\"]\\s*\\)/", $snippet, $m)) {
                return $m[1] ?? null;
            }

            return null;
        };

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
                        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                        $usage[] = [
                            'file' => str_replace(base_path().'/', '', $file->getRealPath()),
                            'line' => $line,
                            'pattern' => $rx['type'],
                            'name' => $extractName($snippet),
                        ];
                    }
                }
            }
        }

        return response()->json(['usage' => $usage]);
    }

    private function listFeatureClasses(): array
    {
        $dir = app_path('Features');
        if (! File::exists($dir)) {
            return [];
        }
        $flags = [];
        foreach (File::files($dir) as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $flags[] = $name;
        }

        return $flags;
    }

    private function descriptions(): array
    {
        return [
            // Core AI
            'ai_description_generation' => 'Enables AI-generated movie/series descriptions.',
            'ai_bio_generation' => 'Enables AI-generated person biographies.',
            'ai_translation_pipeline' => 'Translation pipeline (translate-then-adapt).',
            'ai_quality_scoring' => 'Quality scoring for AI-generated content.',
            'ai_plagiarism_detection' => 'Similarity/plagiarism detection for AI content.',

            // Experiments / versioning
            'generate_v2_pipeline' => 'Experimental new generation flow (WIP/exp).',
            'description_style_packs' => 'Description style packs (modern, critical, playful, …).',
            'recommendation_engine' => 'Recommendations and similarity engine.',

            // Performance & cache
            'redis_cache_descriptions' => 'Cache descriptions in Redis.',
            'redis_cache_bios' => 'Cache biographies in Redis.',
            'prewarm_top_titles' => 'Pre-warm cache for top titles.',

            // i18n
            'multilingual_support' => 'Global enablement of multilingual support.',
            'locale_auto_detect' => 'Automatic user/request locale detection.',
            'glossary_enforcement' => 'Glossary enforcement (non-translatable terms).',

            // Billing / monetization
            'rate_limit_free_plan' => 'Additional rate limits for the Free plan.',
            'webhook_billing' => 'Billing webhooks (RapidAPI/Stripe…).',
            'usage_analytics' => 'Collect and report API usage analytics.',

            // Public API
            'public_search_advanced' => 'Advanced search (fuzzy, aliases, embeddings).',
            'public_jobs_polling' => 'Polling job statuses via public API.',
            'api_v1_deprecation_notice' => 'API v1 deprecation notices.',

            // Moderation / quality
            'human_moderation_required' => 'Require manual moderation before publishing.',
            'toxicity_filter' => 'Toxic/NSFW content filtering.',
            'hallucination_guard' => 'Anti-hallucination guards for AI.',

            // Admin / ops
            'admin_flag_console' => 'Admin console/endpoint for managing flags.',
            'admin_bulk_regeneration' => 'Bulk content regenerations.',
            'admin_edit_lock' => 'Edit locks during batch/ops actions.',
        ];
    }
}
