<?php

namespace App\Filament\Vendedor\Resources\ClienteExternoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Enums\IconPosition;
use App\Models\ClienteExterno;

class Clientesexternos extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Clientes Externos Registrados', ClienteExterno::count())
                ->description('Número total de clientes externos')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ]),

            Stat::make('Clientes con Correo', ClienteExterno::whereNotNull('correo')->count())
                ->description('Clientes que tienen correo registrado')
                ->descriptionIcon('heroicon-m-envelope', IconPosition::Before)
                ->color('success')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ]),

            Stat::make('Clientes con Teléfono', ClienteExterno::whereNotNull('telefono')->count())
                ->description('Clientes que tienen teléfono registrado')
                ->descriptionIcon('heroicon-m-phone', IconPosition::Before)
                ->color('warning')
                ->extraAttributes([
                    'class' => 'cursor-default',
                ])
        ];
    }
}
