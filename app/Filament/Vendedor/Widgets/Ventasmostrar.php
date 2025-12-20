<?php

namespace App\Filament\Vendedor\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Venta;
use Filament\Support\Enums\IconPosition;

class Ventasmostrar extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';
    protected static ?int $sort = 5;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $ventasHoy = Venta::whereDate('fecha', Carbon::today())
            ->where('estado', 'completada')
            ->where('idUsuarioVendedor', Auth::id())
            ->sum('total');

        $ventasSemana = Venta::whereBetween('fecha', [Carbon::now()->subDays(6), Carbon::today()])
            ->where('estado', 'completada')
            ->where('idUsuarioVendedor', Auth::id())
            ->sum('total');

        $ventasMes = Venta::whereBetween('fecha', [Carbon::now()->subDays(30), Carbon::today()])
            ->where('estado', 'completada')
            ->where('idUsuarioVendedor', Auth::id())
            ->sum('total');

        $chartHoy = [50, 75, 60, 90, 70, 95, $ventasHoy];
        $chartSemana = [300, 350, 320, 400, 380, 420, $ventasSemana];
        $chartMes = [1000, 1200, 1500, 1300, 1600, 1750, $ventasMes];

        return [
            Stat::make('Ventas de Hoy', number_format($ventasHoy, 2, '.', ',') . ' Bs')
                ->description(($ventasHoy >= 0 ? '+' : '') . number_format($ventasHoy, 2, '.', ',') . ' Bs respecto a ayer')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->color($ventasHoy > 0 ? 'success' : 'gray')
                ->chart($chartHoy)
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Ventas Últimos 7 Días', number_format($ventasSemana, 2, '.', ',') . ' Bs')
                ->description('Total acumulado semanal')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($ventasSemana > 0 ? 'primary' : 'gray')
                ->chart($chartSemana),

            Stat::make('Ventas Últimos 30 Días', number_format($ventasMes, 2, '.', ',') . ' Bs')
                ->description('Total acumulado mensual')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($ventasMes > 0 ? 'info' : 'gray')
                ->chart($chartMes),
        ];
    }
}
