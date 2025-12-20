<?php

namespace App\Filament\Vendedor\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Venta;
use Flowframe\AdvancedTable\Traits\HasAdvancedFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Livewire\Attributes\Reactive;
use Filament\Support\RawJs;

class Ventasanalisis extends ChartWidget
{
    protected static ?string $heading = 'Análisis de Ventas en Bs';
    protected static ?int $sort = 3;
    protected static bool $isLazy = true;

    public ?string $filter = '7_days';

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
        $startDate = match ($this->filter) {
            'today' => Carbon::today(),
            '7_days' => Carbon::now()->subDays(7),
            '30_days' => Carbon::now()->subDays(30),
            '60_days' => Carbon::now()->subDays(60),
            '90_days' => Carbon::now()->subDays(90),
            'this_month' => Carbon::now()->startOfMonth(),
            'last_month' => Carbon::now()->subMonth()->startOfMonth(),
            default => Carbon::now()->subDays(30),
        };

        $endDate = match ($this->filter) {
            'today' => Carbon::today()->endOfDay(),
            'this_month' => Carbon::now()->endOfMonth(),
            'last_month' => Carbon::now()->subMonth()->endOfMonth(),
            default => Carbon::now(),
        };

        $ventas = Venta::query()
            ->where('idUsuarioVendedor', Auth::id())
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$startDate, $endDate])
            ->selectRaw('DATE(fecha) as dia, COUNT(*) as total_ventas, SUM(total) as monto_total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $fechas = collect();
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $fechas->push($currentDate->format('Y-m-d'));
            $currentDate->addDay();
        }

        $ventasData = $fechas->map(function ($fecha) use ($ventas) {
            $venta = $ventas->firstWhere('dia', $fecha);
            return $venta && $venta->total_ventas > 0 ? (int)$venta->total_ventas : 0;
        });

        $montoData = $fechas->map(function ($fecha) use ($ventas) {
            $venta = $ventas->firstWhere('dia', $fecha);
            return $venta && $venta->monto_total > 0 ? (float)$venta->monto_total : 0;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Monto Total (Bs)',
                    'data' => $montoData,
                    'borderColor' => '#36A2EB',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#36A2EB',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Número de Ventas',
                    'data' => $ventasData,
                    'borderColor' => '#FF6384',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'tension' => 0.4,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => '#FF6384',
                    'borderWidth' => 2,
                    'yAxisID' => 'y1',
                    'borderDash' => [5, 5],
                ],
            ],
            'labels' => $fechas,
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'legend' => ['position' => 'top'],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                        'callbacks' => [
                            'label' => RawJs::make(<<<'JS'
                                function(context) {
                                    const value = context.parsed.y ?? 0;
                                    const label = context.dataset.label || '';
                                    if (context.dataset.yAxisID === 'y') {
                                        return `${label}: ${new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(value)}`;
                                    } else {
                                        return `${label}: ${value} venta${value === 1 ? '' : 's'}`;
                                    }
                                }
                            JS),
                        ],
                    ],
                    'title' => [
                        'display' => true,
                        'text' => 'Análisis Diario de Ventas'
                    ],
                ],
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                        'title' => ['display' => true, 'text' => 'Monto Total (Bs)'],
                        'ticks' => [
                            'callback' => RawJs::make(<<<'JS'
                                function(value) {
                                    return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(value);
                                }
                            JS),
                        ],
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'title' => ['display' => true, 'text' => 'Número de Ventas'],
                        'grid' => ['drawOnChartArea' => false],
                        'ticks' => [
                            'stepSize' => 1,
                            'precision' => 0,
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
}
