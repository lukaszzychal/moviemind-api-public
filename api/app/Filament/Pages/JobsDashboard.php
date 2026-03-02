<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FailedJobsWidget;
use App\Filament\Widgets\JobsChart;
use App\Filament\Widgets\RecentJobsWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\WorkersStatusWidget;
use Filament\Pages\Page;

class JobsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $title = 'Jobs Dashboard';

    protected static string $view = 'filament.pages.jobs-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            JobsChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            WorkersStatusWidget::class,
            RecentJobsWidget::class,
            FailedJobsWidget::class,
        ];
    }
}
