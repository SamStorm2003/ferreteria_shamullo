<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\Venta;
use App\Models\Compra;
use App\Models\Promocion;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;
use App\Models\Almacen;
use App\Models\Proveedor;

class Productosanalisis extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
    protected static ?int $sort = 2;
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Productos', Producto::count())
                ->description('Productos activos')
                ->descriptionIcon('heroicon-m-cube', \Filament\Support\Enums\IconPosition::Before)
                ->color('primary'),

            Stat::make('Stock Total', StockAlmacen::sum('cantidad'))
                ->description('Unidades en almacenes')
                ->descriptionIcon('heroicon-m-archive-box', \Filament\Support\Enums\IconPosition::Before)
                ->color('success')
                ->chart(StockAlmacen::pluck('cantidad')->take(7)->toArray()),

            Stat::make('Ventas Completadas', Venta::where('estado', 'completada')->count())
                ->description('Ventas exitosas')
                ->descriptionIcon('heroicon-m-check-circle', \Filament\Support\Enums\IconPosition::Before)
                ->color('success')
                ->chart(Venta::where('estado', 'completada')->groupBy('fecha')->pluck(DB::raw('count(*)'))->take(7)->toArray()),

            Stat::make('Compras Pendientes', Compra::where('estado', 'pendiente')->count())
                ->description('Compras por procesar')
                ->descriptionIcon('heroicon-m-clock', \Filament\Support\Enums\IconPosition::Before)
                ->color('warning'),

            Stat::make('Promociones Activas', Promocion::where('estado', 'activa')->count())
                ->description('Ofertas vigentes')
                ->descriptionIcon('heroicon-m-ticket', \Filament\Support\Enums\IconPosition::Before)
                ->color('info'),

            Stat::make('Movimientos Recientes', MovimientoInventario::where('fecha', '>=', now()->subDays(7))->count())
                ->description('Últimos 7 días')
                ->descriptionIcon('heroicon-m-arrow-path', \Filament\Support\Enums\IconPosition::Before)
                ->color('primary')
                ->chart(MovimientoInventario::where('fecha', '>=', now()->subDays(7))
                    ->groupBy('fecha')
                    ->pluck(DB::raw('count(*)'))
                    ->toArray()),

            Stat::make('Almacenes Activos', Almacen::count())
                ->description('Almacenes operativos')
                ->descriptionIcon('heroicon-m-building-storefront', \Filament\Support\Enums\IconPosition::Before)
                ->color('info'),

            Stat::make('Proveedores Activos', Proveedor::where('estado', 'activo')->count())
                ->description('Proveedores disponibles')
                ->descriptionIcon('heroicon-m-truck', \Filament\Support\Enums\IconPosition::Before)
                ->color('success'),

            Stat::make('Ventas del Mes (Bs.)', number_format(
                Venta::where('estado', 'completada')
                    ->whereMonth('fecha', now()->month)
                    ->sum('total'),
                2,
                '.',
                ','
            ))
                ->description('Total en bolivianos este mes')
                ->descriptionIcon('heroicon-m-currency-dollar', \Filament\Support\Enums\IconPosition::Before)
                ->color('emerald'),
        ];
    }
}
