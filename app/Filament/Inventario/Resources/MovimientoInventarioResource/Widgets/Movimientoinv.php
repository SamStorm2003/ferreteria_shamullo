<?php

namespace App\Filament\Inventario\Resources\MovimientoInventarioResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use App\Models\MovimientoInventario;
use Carbon\Carbon;

class Movimientoinv extends BaseWidget
{
    protected ?string $heading = 'Estadísticas de Movimientos de Inventario'; 

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $movementsToday = Trend::model(MovimientoInventario::class)
            ->between(
                start: Carbon::today(),
                end: Carbon::today()->endOfDay()
            )
            ->perDay()
            ->count()
            ->first()
            ->aggregate ?? 0;
        $totalQuantity = MovimientoInventario::sum('cantidad') ?? 0;
        $totalCost = MovimientoInventario::selectRaw('SUM(cantidad * costo_unitario) as total')
            ->value('total') ?? 0;

        return [
            Stat::make('Movimientos Hoy', $movementsToday)
                ->description('Movimientos registrados hoy')
                ->color('success')
                ->icon('heroicon-o-arrows-up-down'),
            Stat::make('Cantidad Total Movida', $totalQuantity)
                ->description('Unidades totales movidas')
                ->color('primary')
                ->icon('heroicon-o-cube'),
            Stat::make('Costo Total', 'Bs.' . number_format($totalCost, 2))
                ->description('Costo total de movimientos')
                ->color('warning')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}