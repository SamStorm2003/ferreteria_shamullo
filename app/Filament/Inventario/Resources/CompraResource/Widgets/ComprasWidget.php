<?php

namespace App\Filament\Inventario\Resources\CompraResource\Widgets;

use App\Models\Compra;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class ComprasWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $query = Compra::query();
        if (!Auth::user()->hasRole('Super Admin')) {
            $query->where('idAlmacen', Auth::user()->idAlmacen);
        }
        $start = now()->subMonths(12);
        $end = now();
        $totalComprasTrend = Trend::query($query->clone())
            ->dateColumn('created_at')
            ->between($start, $end)
            ->perMonth()
            ->sum('total')
            ->map(fn(TrendValue $value) => floatval($value->aggregate));
        $completadasTrend = Trend::query($query->clone()->where('estado', 'completada'))
            ->dateColumn('created_at')
            ->between($start, $end)
            ->perMonth()
            ->count()
            ->map(fn(TrendValue $value) => floatval($value->aggregate));
        $pendientesTrend = Trend::query($query->clone()->where('estado', 'pendiente'))
            ->dateColumn('created_at')
            ->between($start, $end)
            ->perMonth()
            ->count()
            ->map(fn(TrendValue $value) => floatval($value->aggregate));
        $canceladasTrend = Trend::query($query->clone()->where('estado', 'cancelada'))
            ->dateColumn('created_at')
            ->between($start, $end)
            ->perMonth()
            ->count()
            ->map(fn(TrendValue $value) => floatval($value->aggregate));
        return [
            Stat::make('Total Compras', number_format($query->sum('total'), 2, '.', '') . ' Bs')
                ->description('Monto total de todas las compras')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart($totalComprasTrend->toArray()),
            Stat::make('Compras Completadas', $query->where('estado', 'completada')->count())
                ->description('Número de compras finalizadas')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('primary')
                ->chart($completadasTrend->toArray()),
            Stat::make('Compras Pendientes', $query->where('estado', 'pendiente')->count())
                ->description('Compras en proceso')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($pendientesTrend->toArray()),
            Stat::make('Compras Canceladas', $query->where('estado', 'cancelada')->count())
                ->description('Compras anuladas')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart($canceladasTrend->toArray()),
        ];
    }
}
