<?php

namespace App\Filament\Widgets;

use App\Models\Job;
use Filament\Widgets\ChartWidget;

class JobsChart extends ChartWidget
{
    protected static ?string $heading = 'Jobs by Queue';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $data = Job::select('queue')
            ->selectRaw('count(*) as count')
            ->groupBy('queue')
            ->pluck('count', 'queue')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Pending Jobs',
                    'data' => array_values($data),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
