<?php

namespace App\Filament\Vendedor\Resources\ReembolsosResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Reembolsos;

class Rembolsoswidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalReembolsos = Reembolsos::count();
        $montoAprobados = Reembolsos::where('estado', 'aprobado')->sum('monto');
        $pendientes = Reembolsos::where('estado', 'pendiente')->count();

        return [
            Stat::make('Total de Reembolsos', $totalReembolsos)
                ->description('Cantidad registrada')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),

            Stat::make('Monto Aprobado', 'Bs. ' . number_format($montoAprobados, 2, '.', ','))
                ->description('Monto total de reembolsos aprobados')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Pendientes', $pendientes)
                ->description('Reembolsos aún sin aprobar')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
