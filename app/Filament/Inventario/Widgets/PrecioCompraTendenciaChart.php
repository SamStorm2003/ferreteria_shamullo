<?php

namespace App\Filament\Inventario\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\DetalleCompra;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Support\RawJs;

class PrecioCompraTendenciaChart extends ChartWidget
{
    protected static ?string $heading = 'Precio de Compra - Tendencia Lineal';
    protected static ?string $description = 'Historial y análisis de tendencia de precios de compra';
    protected static bool $isLazy = true;
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $registros = DetalleCompra::with('producto')
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        if ($registros->isEmpty()) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $agrupados = $registros->groupBy(function ($item) {
            return $item->created_at->format('Y-m-d');
        });

        $labels = [];
        $data = [];

        foreach ($agrupados as $fecha => $detalles) {
            $labels[] = $fecha;
            $promedioCosto = $detalles->avg('costo_unitario');
            $data[] = round($promedioCosto, 2);
        }

        $puntos = [];
        foreach (array_values($data) as $i => $y) {
            $puntos[] = [$i, $y];
        }
        $regresion = $this->calcularRegresionLineal($puntos);

        $lineaTendencia = [
            ['x' => 0, 'y' => $regresion['intercept']],
            ['x' => count($puntos) - 1, 'y' => $regresion['intercept'] + $regresion['slope'] * (count($puntos) - 1)],
        ];

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Precio Promedio de Compra',
                    'data' => $data,
                    'borderColor' => '#6366F1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'pointRadius' => 4,
                    'tension' => 0.2,
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Tendencia Lineal',
                    'data' => $lineaTendencia,
                    'borderColor' => '#EF4444',
                    'borderWidth' => 2,
                    'borderDash' => [5, 5],
                    'type' => 'line',
                    'showLine' => true,
                    'fill' => false,
                    'pointRadius' => 0,
                    'parsing' => ['xAxisKey' => 'x', 'yAxisKey' => 'y'],
                ],
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['position' => 'top'],
                    'title' => ['display' => true, 'text' => 'Precio de Compra vs Tiempo'],
                    'tooltip' => [
                        'callbacks' => [
                            'label' => RawJs::make(<<<'JS'
                            function(context) {
                                const value = context.parsed.y.toLocaleString('es-VE', { style: 'currency', currency: 'VES' });
                                return context.dataset.label + ': ' + value;
                            }
                        JS),
                        ],
                    ],
                ],
                'scales' => [
                    'x' => [
                        'type' => 'category',
                        'title' => ['display' => true, 'text' => 'Fecha'],
                    ],
                    'y' => [
                        'beginAtZero' => false,
                        'title' => ['display' => true, 'text' => 'Precio Unitario (Bs.)'],
                        'ticks' => [
                            'callback' => RawJs::make(<<<'JS'
                            function(value) {
                                return new Intl.NumberFormat('es-VE', { style: 'currency', currency: 'VES' }).format(value);
                            }
                        JS),
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function calcularRegresionLineal(array $puntos): array
    {
        $n = count($puntos);

        if ($n === 0) {
            return ['slope' => 0, 'intercept' => 0];
        }
        $sumX = $sumY = $sumXY = $sumXX = 0;
        foreach ($puntos as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }
        $denominador = ($n * $sumXX - $sumX * $sumX);
        if ($denominador == 0) {
            $intercept = $n > 0 ? $sumY / $n : 0;
            return ['slope' => 0, 'intercept' => $intercept];
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominador;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return compact('slope', 'intercept');
    }
}
