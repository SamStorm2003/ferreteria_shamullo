<?php

namespace App\Filament\Inventario\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\MovimientoInventario;
use Illuminate\Support\Facades\DB;

class MovimientosInventarioChart extends ChartWidget
{
    protected static ?string $heading = 'Movimientos de Inventario';
    protected static ?int $sort = 4;
    protected static bool $isLazy = true;
    protected static ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $labels = collect();
        for ($i = 6; $i >= 0; $i--) {
            $labels->push(now()->subDays($i)->format('Y-m-d'));
        }

        $movimientos = MovimientoInventario::select(
            DB::raw("DATE(fecha) as dia"),
            'tipo',
            DB::raw("SUM(cantidad) as total")
        )
            ->whereDate('fecha', '>=', now()->subDays(6)->toDateString())
            ->groupBy('dia', 'tipo')
            ->orderBy('dia')
            ->get();

        $tipos = ['entrada', 'salida', 'ajuste'];
        $datasets = [];

        foreach ($tipos as $tipo) {
            $data = [];

            foreach ($labels as $label) {
                $registro = $movimientos
                    ->where('dia', $label)
                    ->where('tipo', $tipo)
                    ->first();

                $data[] = $registro ? $registro->total : 0;
            }

            $color = match (strtolower($tipo)) {
                'entrada' => '#10B981',
                'salida'  => '#EF4444',
                'ajuste'  => '#F59E0B',
                default   => '#6B7280',
            };

            $datasets[] = [
                'label' => ucfirst($tipo),
                'data' => $data,
                'borderColor' => $color,
                'backgroundColor' => $color . '33',
                'tension' => 0.4,
                'pointRadius' => 3,
                'borderWidth' => 2,
            ];
        }

        $labelsText = $labels->map(fn($date) => \Carbon\Carbon::parse($date)->format('d M'));

        return [
            'labels' => $labelsText,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
