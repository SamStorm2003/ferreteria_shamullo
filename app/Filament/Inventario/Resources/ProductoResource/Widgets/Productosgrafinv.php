<?php

namespace App\Filament\Inventario\Resources\ProductoResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\StockAlmacen;
use App\Models\Almacen;
use Carbon\Carbon;

class Productosgrafinv extends BaseWidget
{
    protected ?string $heading = 'Estadísticas de Productos';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $todayCount = Trend::model(Producto::class)
            ->between(
                start: Carbon::today(),
                end: Carbon::today()->endOfDay()
            )
            ->perDay()
            ->count()
            ->first()
            ->aggregate ?? 0;
        $categoryCount = Producto::select('idCategoria')
            ->groupBy('idCategoria')
            ->with('categoria')
            ->get()
            ->count();
        $totalStock = StockAlmacen::sum('cantidad') ?? 0;
        return [
            Stat::make('Productos Creados Hoy', $todayCount)
                ->description('Productos registrados en las últimas 24 horas')
                ->color('success')
                ->icon('heroicon-o-plus-circle'),
            Stat::make('Categorías con Productos', $categoryCount)
                ->description('Número de categorías con al menos un producto')
                ->color('primary')
                ->icon('heroicon-o-tag'),
            Stat::make('Stock Total en Almacenes', $totalStock)
                ->description('Cantidad total de productos en todos los almacenes')
                ->color('warning')
                ->icon('heroicon-o-archive-box'),
        ];
    }
}
