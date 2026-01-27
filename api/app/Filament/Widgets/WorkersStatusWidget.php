<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;

class WorkersStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.workers-status-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    // Poll every 5 seconds to keep status live
    protected $pollingInterval = '5s';

    public function getViewData(): array
    {
        return [
            'masters' => $this->getMasters(),
            'supervisors' => $this->getSupervisors(),
        ];
    }

    protected function getMasters(): Collection
    {
        if (! interface_exists(MasterSupervisorRepository::class)) {
            return collect();
        }

        try {
            return collect(app(MasterSupervisorRepository::class)->all())->sortBy('name');
        } catch (\Exception $e) {
            return collect();
        }
    }

    protected function getSupervisors(): Collection
    {
        if (! interface_exists(SupervisorRepository::class)) {
            return collect();
        }

        try {
            return collect(app(SupervisorRepository::class)->all())->sortBy('name');
        } catch (\Exception $e) {
            return collect();
        }
    }
}
