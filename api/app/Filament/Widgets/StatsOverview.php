<?php

namespace App\Filament\Widgets;

use App\Enums\ReportStatus;
use App\Models\AiJob;
use App\Models\FailedJob;
use App\Models\Movie;
use App\Models\Person;
use App\Models\Report;
use App\Models\TvSeries;
use App\Models\TvShow;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected static ?string $pollingInterval = '5s'; // Poll every 5 seconds

    protected function getStats(): array
    {
        return [
            Stat::make('Total Movies', Movie::count())
                ->description('Movies in database')
                ->descriptionIcon('heroicon-m-film')
                ->color('success'),

            Stat::make('Total People', Person::count())
                ->description('People in database')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('Total TV Series', TvSeries::count())
                ->description('TV Series in database')
                ->descriptionIcon('heroicon-m-tv')
                ->color('primary'),

            Stat::make('Total TV Shows', TvShow::count())
                ->description('TV Shows in database')
                ->descriptionIcon('heroicon-m-video-camera')
                ->color('primary'),

            Stat::make('Pending Reports', Report::where('status', ReportStatus::PENDING)->count())
                ->description('Reports awaiting review')
                ->descriptionIcon('heroicon-m-flag')
                ->color('warning'),

            Stat::make('Pending Jobs', AiJob::where('status', 'PENDING')->count())
                ->description('Jobs in queue')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Failed Jobs (24h)', FailedJob::where('failed_at', '>=', now()->subDay())->count())
                ->description('Errors in last 24h')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
