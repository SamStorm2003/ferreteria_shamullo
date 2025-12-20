<?php

namespace App\Filament\Vendedor\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Venta;

class Ventaspredicion extends ChartWidget
{
    protected static ?string $heading = 'Predicción y Resumen de Ventas (Bs)';
    protected static ?int $sort = 4;
    protected static bool $isLazy = true;

    protected function getData(): array
    {
        $hoy = Carbon::today();
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        $ventas = Venta::query()
            ->where('estado', 'completada')
            ->where('idUsuarioVendedor', Auth::id())
            ->whereBetween('fecha', [$startDate, $endDate])
            ->selectRaw("DATE(fecha) as dia, SUM(total) as monto_total")
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();
        $fechas = collect();
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $fechas->push($current->format('Y-m-d'));
            $current->addDay();
        }

        $ventasReales = $fechas->map(function ($fecha) use ($ventas) {
            $venta = $ventas->firstWhere('dia', $fecha);
            return $venta ? floatval($venta->monto_total) : 0;
        });

        $predicciones = [];
        foreach ($ventasReales as $index => $valor) {
            $window = $ventasReales->slice(max(0, $index - 6), 7);
            $promedio = $window->count() ? $window->avg() : 0;
            $predicciones[] = round($promedio, 2);
        }

        $ventasHoy = $ventasReales->last();
        $ventasSemana = $ventasReales->slice(-7)->sum();
        $ventasMes = $ventasReales->sum();

        return [
            'datasets' => [
                [
                    'label' => 'Ventas reales (Bs)',
                    'data' => $ventasReales,
                    'borderColor' => '#36A2EB',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'tension' => 0.4,
                    'pointRadius' => 3,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Predicción (Promedio 7 días)',
                    'data' => $predicciones,
                    'borderColor' => '#FFCE56',
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
                    'borderDash' => [5, 5],
                    'tension' => 0.4,
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $fechas,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['position' => 'top'],
                    'title' => ['display' => true, 'text' => 'Ventas Reales y Predicción en Bs'],
                    'tooltip' => [
                        'callbacks' => [
                            'label' => \Illuminate\Support\Js::from("function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                label += new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(context.parsed.y);
                                return label;
                            }")
                        ]
                    ]
                ],
                'scales' => [
                    'y' => [
                        'title' => ['display' => true, 'text' => 'Monto (Bs)'],
                        'beginAtZero' => true,
                    ],
                ],
            ],
            'summary' => [
                'ventas_hoy' => round($ventasHoy, 2),
                'ventas_semana' => round($ventasSemana, 2),
                'ventas_mes' => round($ventasMes, 2),
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
