<?php

namespace App\Filament\Inventario\Resources\PromocionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use App\Models\Promocion;
use Carbon\Carbon;

class Promocionesinv extends BaseWidget
{
    protected ?string $heading = 'Estadísticas de Promociones';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeToday = Trend::query(Promocion::where('estado', 'activa'))
            ->between(
                start: Carbon::today(),
                end: Carbon::today()->endOfDay()
            )
            ->perDay()
            ->count()
            ->first()
            ->aggregate ?? 0;
        $productsWithPromos = Promocion::select('idProducto')
            ->whereNotNull('idProducto')
            ->distinct()
            ->count();
        $averageDiscount = Promocion::avg('descuento') ?? 0;

        return [
            Stat::make('Promociones Activas Hoy', $activeToday)
                ->description('Promociones activas creadas hoy')
                ->color('success')
                ->icon('heroicon-o-sparkles'),
            Stat::make('Productos con Promociones', $productsWithPromos)
                ->description('Número de productos con al menos una promoción')
                ->color('primary')
                ->icon('heroicon-o-tag'),
            Stat::make('Descuento Promedio', number_format($averageDiscount, 2) . '%')
                ->description('Descuento promedio de todas las promociones')
                ->color('warning')
                ->icon('heroicon-o-currency-dollar'),
        ];
    }
}