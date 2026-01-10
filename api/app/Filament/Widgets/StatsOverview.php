<?php

namespace App\Filament\Widgets;

use App\Models\Movie;
use App\Models\Person;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalMovies = Movie::count();
        $totalPeople = Person::count();
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        return [
            Stat::make('Total Movies', $totalMovies)
                ->description('Movies in database')
                ->descriptionIcon('heroicon-m-film')
                ->color('success'),
            Stat::make('Total People', $totalPeople)
                ->description('People in database')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make('Pending Jobs', $pendingJobs)
                ->description('Jobs in queue')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Failed Jobs (24h)', $failedJobs)
                ->description('Failed in last 24 hours')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
