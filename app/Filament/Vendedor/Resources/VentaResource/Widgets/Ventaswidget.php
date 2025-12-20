<?php

namespace App\Filament\Vendedor\Resources\VentaResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use App\Models\Venta;
use App\Models\ClienteExterno;
use App\Models\Producto;
use App\Models\StockAlmacen;

class Ventaswidget extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $totalSales = Venta::where('estado', 'completada')->sum('total');
        $salesTrend = Trend::model(Venta::class)
            ->between(now()->subMonth(), now())
            ->perDay()
            ->sum('total')
            ->map(fn(TrendValue $value) => $value->aggregate)
            ->toArray();

        $totalClients = ClienteExterno::count();
        $clientsTrend = Trend::model(ClienteExterno::class)
            ->between(now()->subMonth(), now())
            ->perDay()
            ->count()
            ->map(fn(TrendValue $value) => $value->aggregate)
            ->toArray();

        $avgSale = Venta::where('estado', 'completada')->avg('total');
        $avgSaleTrend = Trend::model(Venta::class)
            ->between(now()->subMonth(), now())
            ->perDay()
            ->average('total')
            ->map(fn(TrendValue $value) => $value->aggregate)
            ->toArray();

        $productsSold = Venta::where('estado', 'completada')
            ->join('detalle_ventas', 'ventas.idVenta', '=', 'detalle_ventas.idVenta')
            ->sum('detalle_ventas.cantidad');
        $productsTrend = Trend::query(
            Venta::where('estado', 'completada')
                ->join('detalle_ventas', 'ventas.idVenta', '=', 'detalle_ventas.idVenta')
        )
            ->dateColumn('ventas.created_at')
            ->between(now()->subMonth(), now())
            ->perDay()
            ->sum('detalle_ventas.cantidad')
            ->map(fn(TrendValue $value) => $value->aggregate)
            ->toArray();

        return [
            Stat::make('Total Ventas', number_format($totalSales, 2))
                ->description('Ventas completadas')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($salesTrend)
                ->color('success'),
            Stat::make('Clientes Externos', $totalClients)
                ->description('Total registrados')
                ->descriptionIcon('heroicon-m-users')
                ->chart($clientsTrend)
                ->color('primary'),
            Stat::make('Promedio por Venta', number_format($avgSale, 2))
                ->description('Valor promedio')
                ->descriptionIcon('heroicon-m-calculator')
                ->chart($avgSaleTrend)
                ->color('info'),
            Stat::make('Productos Vendidos', $productsSold)
                ->description('Total unidades')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->chart($productsTrend)
                ->color('warning'),
        ];
    }
}
