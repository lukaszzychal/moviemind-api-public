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

    private function descriptions(): array
    {
        return [
            // Core AI
            'ai_description_generation' => 'Włącza generowanie opisów filmów/seriali przez AI.',
            'ai_bio_generation' => 'Włącza generowanie biografii osób przez AI.',
            'ai_translation_pipeline' => 'Pipeline tłumaczeń (translate-then-adapt).',
            'ai_quality_scoring' => 'Ocena jakości treści wygenerowanych przez AI.',
            'ai_plagiarism_detection' => 'Wykrywanie podobieństw/plagiatu treści AI.',

            // Experiments / versioning
            'generate_v2_pipeline' => 'Eksperymentalny nowy flow generowania opisów (WIP/exp).',
            'description_style_packs' => 'Style opisów (modern, critical, playful, …).',
            'recommendation_engine' => 'System rekomendacji i podobieństw.',

            // Performance & cache
            'redis_cache_descriptions' => 'Cache opisów w Redis.',
            'redis_cache_bios' => 'Cache biografii w Redis.',
            'prewarm_top_titles' => 'Pre-warming cache dla najpopularniejszych tytułów.',

            // i18n
            'multilingual_support' => 'Globalne włączenie wielojęzyczności.',
            'locale_auto_detect' => 'Automatyczna detekcja języka użytkownika/żądania.',
            'glossary_enforcement' => 'Wymuszanie glosariusza (terminy bez tłumaczeń).',

            // Billing / monetization
            'rate_limit_free_plan' => 'Dodatkowe limity dla planu Free.',
            'webhook_billing' => 'Webhooki billingowe (RapidAPI/Stripe…).',
            'usage_analytics' => 'Zbieranie i raportowanie użycia API.',

            // Public API
            'public_search_advanced' => 'Zaawansowane wyszukiwanie (fuzzy, aliasy, embeddings).',
            'public_jobs_polling' => 'Polling statusów zadań (jobs) po publicznym API.',
            'api_v1_deprecation_notice' => 'Komunikaty deprecjacji API v1.',

            // Moderation / quality
            'human_moderation_required' => 'Ręczna moderacja treści przed publikacją.',
            'toxicity_filter' => 'Filtr treści toksycznych/NSFW.',
            'hallucination_guard' => 'Straże anty-halucynacyjne dla AI.',

            // Admin / ops
            'admin_flag_console' => 'Konsola/endpoint admin do zarządzania flagami.',
            'admin_bulk_regeneration' => 'Masowe regeneracje treści.',
            'admin_edit_lock' => 'Blokady edycji podczas operacji wsadowych.',
        ];
    }
}
