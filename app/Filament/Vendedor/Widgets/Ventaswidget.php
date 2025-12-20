<?php

namespace App\Filament\Vendedor\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\StockAlmacen;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Ventaswidget extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
        protected static ?int $sort = 2;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $almacenId = Auth::user()->idAlmacen;
        $ventasCompletadas = Venta::where('idUsuarioVendedor', Auth::id())
            ->where('estado', 'completada')
            ->count();
        $totalProductosVendidos = DetalleVenta::whereIn('idVenta', function ($query) {
            $query->from('ventas')
                ->select('idVenta')
                ->where('idUsuarioVendedor', Auth::id())
                ->where('estado', 'completada');
        })->sum('cantidad');
        $stockBajo = StockAlmacen::where('idAlmacen', $almacenId)
            ->where('cantidad', '<', 5)
            ->count();
        $ventasUltimosDias = [];
        for ($i = 6; $i >= 0; $i--) {
            $fecha = Carbon::now()->subDays($i)->toDateString();
            $ventasUltimosDias[] = Venta::where('idUsuarioVendedor', Auth::id())
                ->whereDate('fecha', $fecha)
                ->count();
        }
        return [
            Stat::make('Ventas Completadas', number_format($ventasCompletadas))
                ->description('Ventas realizadas por ti')
                ->icon('heroicon-m-shopping-cart')
                ->color('success')
                ->chart([rand(5, 10), rand(8, 12), $ventasCompletadas]),
            Stat::make('Productos Vendidos', number_format($totalProductosVendidos))
                ->description('Unidades totales vendidas')
                ->icon('heroicon-m-cube')
                ->color('primary')
                ->chart(array_map(fn() => rand(10, 30), range(1, 7))),
            Stat::make('Productos con Stock Bajo', number_format($stockBajo))
                ->description('Revisa tu inventario')
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->chart([0, 1, 0, 2, 0, 1, $stockBajo]),
            Stat::make('Ventas Últimos 7 Días', count(array_filter($ventasUltimosDias)))
                ->description('Tendencia semanal')
                ->icon('heroicon-m-chart-bar')
                ->color('info')
                ->chart($ventasUltimosDias),
        ];
    }
}
