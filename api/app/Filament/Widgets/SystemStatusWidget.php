<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SystemStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-status-widget';

    protected static ?int $sort = -1; // Display at the top

    public array $status = [];

    public function mount(): void
    {
        $this->loadStatus();
    }

    public function loadStatus(): void
    {
        $this->status = Cache::remember('system-status-widget', 60, function () {
            try {
                // We need to bypass the admin auth for this internal call
                // A dedicated internal API client or direct service call would be better in the long run
                $response = Http::withHeader('X-Admin-Token', env('ADMIN_API_TOKEN'))
                    ->get(url('/api/v1/admin/debug/config'));

                if ($response->successful()) {
                    return $response->json('environment');
                }
            } catch (\Exception $e) {
                // Fallback in case of error
            }

            return [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'ai_service_env' => config('services.ai.service'),
                'instance_id' => env('INSTANCE_ID', 'unknown'),
            ];
        });
    }
}
