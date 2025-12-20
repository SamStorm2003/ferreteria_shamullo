<?php

namespace App\Filament\Inventario\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Compra;
use Illuminate\Support\Carbon;

class TotalComprasOverview extends BaseWidget
{
    protected static ?int $sort = 2;
    protected ?string $heading = 'Resumen de Compras';
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $comprasHoy = Compra::whereDate('fecha', Carbon::today())->get();
        $totalHoy = $comprasHoy->sum('total');
        $cantidadHoy = $comprasHoy->count();

        $comprasSemana = Compra::whereBetween('fecha', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->get();
        $totalSemana = $comprasSemana->sum('total');

        $comprasMes = Compra::whereMonth('fecha', Carbon::now()->month)->get();
        $totalMes = $comprasMes->sum('total');
        $cantidadMes = $comprasMes->count();

        $promedioMes = $cantidadMes > 0 ? $totalMes / $cantidadMes : 0;

        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $date = $day->toDateString();
            $labels[] = $day->format('D');
            $data[] = Compra::whereDate('fecha', $date)->sum('total');
        }

        return [
            Stat::make('Hoy', 'Bs. ' . number_format($totalHoy, 2, ',', '.'))
                ->description($cantidadHoy . ' compras realizadas')
                ->color($totalHoy > 0 ? 'success' : 'gray')
                ->chart($data),

            Stat::make('Esta Semana', 'Bs. ' . number_format($totalSemana, 2, ',', '.'))
                ->description('Basado en últimos 7 días')
                ->color('info')
                ->chart(array_slice($data, -3))
                ->descriptionIcon('heroicon-m-currency-dollar'),

            Stat::make('Este Mes', 'Bs. ' . number_format($totalMes, 2, ',', '.'))
                ->description('Bs. ' . number_format($promedioMes, 2, ',', '.') . ' promedio/día')
                ->color('primary')
                ->descriptionIcon('heroicon-m-calendar-days', 'before')
                ->chart($data),
        ];
    }
}
