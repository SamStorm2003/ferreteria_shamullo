<?php

namespace App\Providers\Filament;

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
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;
use Filament\Navigation\NavigationItem;
use Devonab\FilamentEasyFooter\EasyFooterPlugin;
use ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin;

class InventarioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('inventario')
            ->path('inventario')
            ->login()
            ->favicon('logo.ico')
            ->navigationItems([
                NavigationItem::make('Ir a Admin')
                    ->url('/admin')
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn() => auth()->check() && (auth()->user()->hasRole('Super Admin'))),
                NavigationItem::make('Ir a Vendedor')
                    ->url('/vendedor')
                    ->icon('heroicon-o-user')
                    ->visible(fn() => auth()->check() && (auth()->user()->hasRole('Super Admin'))),
            ])
            ->brandName('Panel del Inventario')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Inventario/Resources'), for: 'App\\Filament\\Inventario\\Resources')
            ->discoverPages(in: app_path('Filament/Inventario/Pages'), for: 'App\\Filament\\Inventario\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Inventario/Widgets'), for: 'App\\Filament\\Inventario\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                //Widgets\FilamentInfoWidget::class,
            ])
            ->plugins([
                FilamentBackgroundsPlugin::make(),
                \Hasnayeen\Themes\ThemesPlugin::make(),
                FilamentEditProfilePlugin::make()
                    ->slug('my-profile')
                    ->setTitle('Mi Perfil')
                    ->setNavigationLabel('Mi Perfil ')
                    ->setNavigationGroup('Groupo de Perfil')
                    ->setIcon('heroicon-o-user'),
                \MartinPetricko\FilamentSentryFeedback\FilamentSentryFeedbackPlugin::make(),
                FilamentSpatieLaravelHealthPlugin::make(),
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
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
