<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use App\Models\Movie;
use App\Models\OutgoingWebhook;
use App\Models\Person;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AnalyticsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Movies', Movie::count())
                ->description('Processed movies in database')
                ->descriptionIcon('heroicon-m-film')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Total People', Person::count())
                ->description('Actors & Directors')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Active API Keys', ApiKey::where('is_active', true)->count())
                ->description('Active subscriptions')
                ->descriptionIcon('heroicon-m-key')
                ->color('warning'),

            Stat::make('Webhooks Sent', OutgoingWebhook::where('status', 'sent')->count())
                ->description('Successful webhook deliveries')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('primary'),
        ];
    }
}
