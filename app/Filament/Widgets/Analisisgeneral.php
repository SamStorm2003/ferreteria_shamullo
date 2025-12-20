<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Venta;
use App\Models\Compra;
use App\Models\Reembolsos;
use App\Models\Pago;
use Carbon\Carbon;

class Analisisgeneral extends ChartWidget
{
    protected static ?string $heading = 'Análisis Financiero General Diario (Bs.)';
    protected static ?int $sort = 3;
    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $dias = collect(range(0, 29))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));

        $ventas = [];
        $compras = [];
        $reembolsos = [];
        $pagos = [];
        foreach ($dias as $dia) {
            $ventaDia = Venta::where('estado', 'completada')
                ->whereDate('fecha', $dia)
                ->sum('total');

            $compraDia = Compra::where('estado', 'completada')
                ->whereDate('fecha', $dia)
                ->sum('total');

            $reembolsoDia = Reembolsos::where('estado', 'aprobado')
                ->whereDate('fecha', $dia)
                ->sum('monto');

            $pagoDia = Pago::where('estado', 'aprobado')
                ->whereDate('fecha', $dia)
                ->sum('monto');

            $ventas[] = round($ventaDia, 2);
            $compras[] = round($compraDia, 2);
            $reembolsos[] = round($reembolsoDia, 2);
            $pagos[] = round($pagoDia, 2);
        }

        $labels = $dias->map(fn($d) => Carbon::parse($d)->format('d M'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (Bs.)',
                    'data' => $ventas,
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.3)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => 'rgba(59, 130, 246, 1)',
                ],
                [
                    'label' => 'Compras (Bs.)',
                    'data' => $compras,
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.3)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => 'rgba(34, 197, 94, 1)',
                ],
                [
                    'label' => 'Reembolsos (Bs.)',
                    'data' => $reembolsos,
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.3)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => 'rgba(239, 68, 68, 1)',
                ],
                [
                    'label' => 'Pagos (Bs.)',
                    'data' => $pagos,
                    'borderColor' => 'rgba(250, 204, 21, 1)',
                    'backgroundColor' => 'rgba(250, 204, 21, 0.3)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => 'rgba(250, 204, 21, 1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
