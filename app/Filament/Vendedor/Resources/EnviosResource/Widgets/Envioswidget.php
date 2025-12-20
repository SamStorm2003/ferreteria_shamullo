<?php

namespace App\Filament\Vendedor\Resources\EnviosResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Envios;

class Envioswidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return 'Estadísticas de Envíos';
    }

    protected function getStats(): array
    {
        $baseQuery = Envios::query()->where('estado_envio', '!=', 'entregado');
        if (!Auth::user()->hasRole('Super Admin')) {
            $idAlmacen = Auth::user()->idAlmacen;
            if ($idAlmacen) {
                $baseQuery->whereHas('venta.detalles', function (Builder $q) use ($idAlmacen) {
                    $q->where('idAlmacen', $idAlmacen);
                });
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
        }
        $pendientes = (clone $baseQuery)->where('estado_envio', 'pendiente')->count();
        $pendientesLastWeek = (clone $baseQuery)->where('estado_envio', 'pendiente')
            ->where('created_at', '>=', Carbon::now()->subWeek())
            ->count();
        $enviados = (clone $baseQuery)->where('estado_envio', 'enviado')->count();
        $enviadosLastWeek = (clone $baseQuery)->where('estado_envio', 'enviado')
            ->where('created_at', '>=', Carbon::now()->subWeek())
            ->count();
        $productosPendientes = (clone $baseQuery)->where('estado_envio', 'pendiente')
            ->join('ventas', 'envios.idVenta', '=', 'ventas.idVenta')
            ->join('detalle_ventas', 'ventas.idVenta', '=', 'detalle_ventas.idVenta')
            ->sum('detalle_ventas.cantidad') ?? 0;
        $avgDays = (clone $baseQuery)->whereNotNull('fecha_entrega_estimada')
            ->get()
            ->avg(function ($envio) {
                return Carbon::now()->diffInDays($envio->fecha_entrega_estimada, false);
            });
        $avgDays = $avgDays !== null ? round($avgDays, 1) : 0;
        return [
            Stat::make('Envíos Pendientes', $pendientes)
                ->description($pendientesLastWeek . ' esta semana')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($this->getDailyChart($baseQuery, 'pendiente'))
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('setStatusFilter', { filter: 'pendiente' })",
                ]),

            Stat::make('Envíos en Camino', $enviados)
                ->description($enviadosLastWeek . ' esta semana')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->chart($this->getDailyChart($baseQuery, 'enviado'))
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => "\$dispatch('setStatusFilter', { filter: 'enviado' })",
                ]),

            Stat::make('Productos Pendientes', $productosPendientes)
                ->description('Productos por enviar')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->chart($this->getProductosChart($baseQuery)),

            Stat::make('Días hasta Entrega', $avgDays . ' días')
                ->description('Promedio estimado')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success')
                ->chart($this->getDaysChart($baseQuery)),
        ];
    }

    protected function getDailyChart(Builder $baseQuery, string $estado): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = (clone $baseQuery)->where('estado_envio', $estado)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }
        return array_reverse($data);
    }

    protected function getProductosChart(Builder $baseQuery): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $sum = (clone $baseQuery)->where('estado_envio', 'pendiente')
                ->whereDate('envios.created_at', $date)
                ->join('ventas', 'envios.idVenta', '=', 'ventas.idVenta')
                ->join('detalle_ventas', 'ventas.idVenta', '=', 'detalle_ventas.idVenta')
                ->sum('detalle_ventas.cantidad') ?? 0;
            $data[] = $sum;
        }
        return array_reverse($data);
    }

    protected function getDaysChart(Builder $baseQuery): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $avg = (clone $baseQuery)->whereNotNull('fecha_entrega_estimada')
                ->whereDate('created_at', $date)
                ->get()
                ->avg(function ($envio) {
                    return Carbon::now()->diffInDays($envio->fecha_entrega_estimada, false);
                }) ?? 0;
            $data[] = round($avg, 1);
        }
        return array_reverse($data);
    }
}
