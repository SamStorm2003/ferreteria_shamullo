<?php

namespace App\Filament\Inventario\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Compra;
use Illuminate\Support\Facades\DB;
use Filament\Support\RawJs;
use Carbon\Carbon;

class ComprasChart extends ChartWidget
{
    protected static ?string $heading = 'Análisis de Compras por Día';
    protected static ?int $sort = 3;
    protected static bool $isLazy = true;

    public ?string $filter = '30_days';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hoy',
            '7_days' => 'Últimos 7 días',
            '30_days' => 'Últimos 30 días',
            '60_days' => 'Últimos 60 días',
            '90_days' => 'Últimos 90 días',
            'this_month' => 'Este mes',
            'last_month' => 'Mes anterior',
        ];
    }

    protected function getData(): array
    {
        $query = Compra::query()
            ->select(
                DB::raw('DATE(fecha) as dia'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as cantidad')
            )
            ->groupBy('dia')
            ->orderBy('dia');

        match ($this->filter) {
            'today' => $query->whereDate('fecha', Carbon::today()),
            '7_days' => $query->whereDate('fecha', '>=', Carbon::now()->subDays(7)),
            '30_days' => $query->whereDate('fecha', '>=', Carbon::now()->subDays(30)),
            '60_days' => $query->whereDate('fecha', '>=', Carbon::now()->subDays(60)),
            '90_days' => $query->whereDate('fecha', '>=', Carbon::now()->subDays(90)),
            'this_month' => $query->whereMonth('fecha', Carbon::now()->month),
            'last_month' => $query->whereMonth('fecha', Carbon::now()->subMonth()->month),
            default => $query->whereDate('fecha', '>=', Carbon::now()->subDays(30)),
        };

        $compras = $query->get();

        $labels = $compras->pluck('dia');
        $totales = $compras->pluck('total');
        $cantidades = $compras->pluck('cantidad');

        return [
            'datasets' => [
                [
                    'label' => 'Total Gastado (Bs.)',
                    'data' => $totales,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#3B82F6',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Número de Compras',
                    'data' => $cantidades,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#10B981',
                    'borderWidth' => 2,
                    'yAxisID' => 'y1',
                    'borderDash' => [5, 5],
                ]
            ],
            'labels' => $labels,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => [
                        'position' => 'top',
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                        'callbacks' => [
                            'label' => RawJs::make("
                                function(context) {
                                    const value = context.parsed.y.toLocaleString('es-VE', { style: 'currency', currency: 'VES' });
                                    return context.dataset.label + ': ' + value;
                                }
                            "),
                        ],
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Resumen Diario de Compras (Bs.)'
                    ]
                ],
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'scales' => [
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => ['display' => true, 'text' => 'Monto Total (Bs.)'],
                        'ticks' => [
                            'callback' => RawJs::make("
                                function(value) {
                                    return new Intl.NumberFormat('es-VE', { style: 'currency', currency: 'VES' }).format(value);
                                }
                            "),
                        ],
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => ['display' => true, 'text' => 'Número de Compras'],
                        'grid' => ['drawOnChartArea' => false],
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
