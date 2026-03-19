<?php

namespace App\Providers\Filament;

use App\Filament\Pages\JobsDashboard;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SystemStatusWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->brandName('MovieMind Admin')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                JobsDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                SystemStatusWidget::class,
                StatsOverview::class,
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\RestrictAdminPanel::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Horizon')
                    ->url('/horizon', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-globe-alt')
                    ->group('System')
                    ->sort(3),
                \Filament\Navigation\NavigationItem::make('API Docs')
                    ->url('/docs', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-book-open')
                    ->group('System')
                    ->sort(4),
            ]);
    }
}
