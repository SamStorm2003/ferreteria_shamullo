<?php

namespace App\Exports;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\Pago;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Layout;

class VentasExport implements WithMultipleSheets
{
    protected $tipo;
    protected $fechaInicio;
    protected $fechaFin;

    public function __construct($tipo, $fechaInicio = null, $fechaFin = null)
    {
        $this->tipo = $tipo;
        $this->fechaInicio = $fechaInicio ? Carbon::parse($fechaInicio)->startOfDay()->toDateTimeString() : now()->startOfDay()->toDateTimeString();
        $this->fechaFin = $fechaFin ? Carbon::parse($fechaFin)->endOfDay()->toDateTimeString() : now()->endOfDay()->toDateTimeString();
    }

    public function sheets(): array
    {
        return [
            'Resumen Ventas' => new VentasSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
            'Detalles Ventas' => new DetalleVentasSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
            'Pagos' => new PagosSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
        ];
    }
}

class VentasSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $tipo;
    protected $fechaInicio;
    protected $fechaFin;

    public function __construct($tipo, $fechaInicio, $fechaFin)
    {
        $this->tipo = $tipo;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function collection()
    {
        $query = Venta::query()
            ->leftJoin('users as vendedor', 'ventas.idUsuarioVendedor', '=', 'vendedor.id')
            ->leftJoin('users as cliente', 'ventas.idUsuarioCliente', '=', 'cliente.id')
            ->leftJoin('cliente_externos', 'ventas.idClienteExterno', '=', 'cliente_externos.idClienteExterno')
            ->leftJoin('pagos', 'ventas.idVenta', '=', 'pagos.idVenta')
            ->select(
                'ventas.idVenta',
                DB::raw('COALESCE(vendedor.name, "Sin Vendedor") as vendedor'),
                DB::raw('COALESCE(cliente.name, cliente_externos.nombre, "Sin Cliente") as cliente'),
                'ventas.fecha',
                'ventas.total',
                'ventas.estado',
                'ventas.tipo_entrega',
                DB::raw('COALESCE(ventas.deleted_at, "No Eliminado") as fecha_eliminacion'),
                DB::raw('COALESCE(pagos.metodo, "Sin Pago") as metodo_pago'),
                DB::raw('COALESCE(pagos.estado, "Sin Pago") as estado_pago')
            )
            ->whereNull('ventas.deleted_at');

        if ($this->tipo === 'diario') {
            $query->whereBetween('ventas.fecha', [$this->fechaInicio, Carbon::parse($this->fechaInicio)->endOfDay()->toDateTimeString()]);
        } elseif ($this->tipo === 'ventasfecha') {
            $query->whereBetween('ventas.fecha', [$this->fechaInicio, $this->fechaFin]);
        }

        $ventas = $query->get();

        $detalles = DB::table('detalle_ventas')
            ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
            ->join('almacens', 'detalle_ventas.idAlmacen', '=', 'almacens.idAlmacen')
            ->select(
                'detalle_ventas.idVenta',
                'productos.nombre as producto_nombre',
                'detalle_ventas.cantidad',
                'detalle_ventas.precio_unitario',
                'almacens.nombre as almacen_nombre'
            )
            ->whereNull('detalle_ventas.deleted_at')
            ->whereIn('detalle_ventas.idVenta', $ventas->pluck('idVenta'))
            ->get()
            ->groupBy('idVenta');

        $total_ventas = $ventas->count();
        $total_monto = $ventas->sum('total');
        $ventas_completadas = $ventas->where('estado', 'completada')->count();
        $ventas_pendientes = $ventas->where('estado', 'pendiente')->count();
        $ventas_canceladas = $ventas->where('estado', 'cancelada')->count();
        $ventas_envio = $ventas->where('tipo_entrega', 'envio')->count();
        $ventas_recogida = $ventas->where('tipo_entrega', 'recogida')->count();
        $promedio_venta = $total_ventas > 0 ? $total_monto / $total_ventas : 0;
        $clientes_unicos = $ventas->groupBy('cliente')->count();

        $data = $ventas->map(function ($venta) use ($detalles) {
            $detalle_venta = $detalles->get($venta->idVenta, collect());
            $productos = $detalle_venta->map(function ($detalle) {
                return "{$detalle->producto_nombre}: {$detalle->cantidad} x " . number_format($detalle->precio_unitario, 2);
            })->implode('; ');
            $almacen = $detalle_venta->pluck('almacen_nombre')->unique()->implode(', ') ?: 'Sin Almacén';

            return [
                'idVenta' => $venta->idVenta,
                'vendedor' => $venta->vendedor,
                'cliente' => $venta->cliente,
                'fecha' => $venta->fecha->toDateTimeString(),
                'total' => number_format($venta->total, 2),
                'estado' => $venta->estado,
                'tipo_entrega' => $venta->tipo_entrega,
                'fecha_eliminacion' => $venta->fecha_eliminacion,
                'productos_comprados' => $productos ?: 'Sin Productos',
                'almacen' => $almacen,
                'metodo_pago' => $venta->metodo_pago,
                'estado_pago' => $venta->estado_pago,
            ];
        });

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Ventas', $total_ventas, '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Monto', '', number_format($total_monto, 2), '', '', '', '', '', '', '', '', '']),
            new Collection(['Ventas Completadas', '', '', $ventas_completadas, '', '', '', '', '', '', '', '']),
            new Collection(['Ventas Pendientes', '', '', $ventas_pendientes, '', '', '', '', '', '', '', '']),
            new Collection(['Ventas Canceladas', '', '', $ventas_canceladas, '', '', '', '', '', '', '', '']),
            new Collection(['Ventas por Envío', '', '', '', $ventas_envio, '', '', '', '', '', '', '']),
            new Collection(['Ventas por Recogida', '', '', '', $ventas_recogida, '', '', '', '', '', '', '']),
            new Collection(['Promedio por Venta', '', '', '', '', number_format($promedio_venta, 2), '', '', '', '', '', '']),
            new Collection(['Clientes Únicos', '', '', '', '', '', $clientes_unicos, '', '', '', '', '']),
        ]);

        return $data->concat($summary);
    }

    public function headings(): array
    {
        return [
            'ID Venta',
            'Vendedor',
            'Cliente',
            'Fecha',
            'Total',
            'Estado',
            'Tipo Entrega',
            'Fecha Eliminación',
            'Productos Comprados',
            'Almacén',
            'Método de Pago',
            'Estado de Pago',
        ];
    }

    public function title(): string
    {
        return 'Resumen Ventas';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $dataRows = $highestRow - 10;
        for ($i = 2; $i <= $dataRows; $i++) {
            $estado_pago = $sheet->getCell("L$i")->getValue();
            if ($estado_pago === 'aprobado') {
                $sheet->getStyle("A$i:L$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFB2DFDB']],
                ]);
            } elseif ($estado_pago === 'pendiente') {
                $sheet->getStyle("A$i:L$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE082']],
                ]);
            } elseif ($estado_pago === 'rechazado') {
                $sheet->getStyle("A$i:L$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCDD2']],
                ]);
            }
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1565C0']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            ],
            "A" . ($highestRow - 9) . ":L{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBBDEFB']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $labels = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Resumen Ventas!$D$4:$D$6', null, 3), // Labels: Completadas, Pendientes, Canceladas
                ];
                $categories = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Resumen Ventas!$C$4:$C$6', null, 3), // Categories
                ];
                $values = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Resumen Ventas!$D$4:$D$6', null, 3), // Values
                ];

                $dataSeries = new DataSeries(
                    DataSeries::TYPE_BARCHART,
                    DataSeries::GROUPING_STANDARD,
                    range(0, count($values) - 1),
                    $labels,
                    $categories,
                    $values
                );

                $plotArea = new PlotArea(null, [$dataSeries]);
                $layout = new Layout();
                $chart = new Chart(
                    'Ventas por Estado',
                    new Title('Ventas por Estado'),
                    new Legend(Legend::POSITION_RIGHT, null, false),
                    $plotArea
                );

                $chart->setTopLeftPosition('N2')->setBottomRightPosition('T15');
                $sheet->addChart($chart);
            },
        ];
    }
}

class DetalleVentasSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    protected $tipo;
    protected $fechaInicio;
    protected $fechaFin;
    protected $resumen_productos;
    protected $top_productos;

    public function __construct($tipo, $fechaInicio, $fechaFin)
    {
        $this->tipo = $tipo;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function collection()
    {
        $query = Venta::query()
            ->join('detalle_ventas', 'ventas.idVenta', '=', 'detalle_ventas.idVenta')
            ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
            ->join('almacens', 'detalle_ventas.idAlmacen', '=', 'almacens.idAlmacen')
            ->leftJoin('stock_almacens', function ($join) {
                $join->on('detalle_ventas.idProducto', '=', 'stock_almacens.idProducto')
                    ->on('detalle_ventas.idAlmacen', '=', 'stock_almacens.idAlmacen');
            })
            ->select(
                'ventas.idVenta',
                'productos.nombre as producto',
                'almacens.nombre as almacen',
                'detalle_ventas.cantidad',
                'detalle_ventas.precio_unitario',
                DB::raw('detalle_ventas.cantidad * detalle_ventas.precio_unitario as subtotal'),
                'ventas.fecha',
                DB::raw('COALESCE(stock_almacens.costo_unitario, 0) as costo_unitario')
            )
            ->whereNull('ventas.deleted_at')
            ->whereNull('detalle_ventas.deleted_at');

        if ($this->tipo === 'diario') {
            $query->whereBetween('ventas.fecha', [$this->fechaInicio, Carbon::parse($this->fechaInicio)->endOfDay()->toDateTimeString()]);
        } elseif ($this->tipo === 'ventasfecha') {
            $query->whereBetween('ventas.fecha', [$this->fechaInicio, $this->fechaFin]);
        }

        $data = $query->get();

        $total_detalles = $data->count();
        $total_subtotal = $data->sum('subtotal');
        $total_margen = $data->sum(function ($item) {
            return ($item->subtotal - ($item->costo_unitario * $item->cantidad));
        });

        $mappedData = $data->map(function ($item) {
            $margen = $item->subtotal - ($item->costo_unitario * $item->cantidad);
            return [
                'idVenta' => $item->idVenta,
                'producto' => $item->producto,
                'almacen' => $item->almacen,
                'cantidad' => $item->cantidad,
                'precio_unitario' => number_format($item->precio_unitario, 2),
                'subtotal' => number_format($item->subtotal, 2),
                'margen_ganancia' => number_format($margen, 2),
                'fecha' => $item->fecha->toDateTimeString(),
            ];
        });

        $this->resumen_productos = $data->groupBy('producto')->map(function ($items) {
            return [
                'producto' => $items->first()->producto,
                'cantidad' => $items->sum('cantidad'),
                'subtotal' => number_format($items->sum('subtotal'), 2),
                'margen' => number_format($items->sum(function ($item) {
                    return ($item->subtotal - ($item->costo_unitario * $item->cantidad));
                }), 2),
            ];
        })->values();

        $this->top_productos = $this->resumen_productos->sortByDesc('cantidad')->take(5);

        $producto_summary = collect([new Collection(['Resumen por Producto', '', '', '', '', '', '', ''])])
            ->concat($this->resumen_productos->map(function ($item) {
                return new Collection(['', $item['producto'], '', $item['cantidad'], '', $item['subtotal'], $item['margen'], '']);
            }));

        $top_summary = collect([new Collection(['Top 5 Productos', '', '', '', '', '', '', ''])])
            ->concat($this->top_productos->map(function ($item) {
                return new Collection(['', $item['producto'], '', $item['cantidad'], '', $item['subtotal'], $item['margen'], '']);
            }));

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '']),
            new Collection(['Total Detalles', $total_detalles, '', '', '', '', '', '']),
            new Collection(['Total Subtotal', '', '', '', number_format($total_subtotal, 2), '', '', '']),
            new Collection(['Total Margen', '', '', '', '', number_format($total_margen, 2), '', '']),
        ]);

        return $mappedData->concat($summary)->concat($producto_summary)->concat($top_summary);
    }

    public function headings(): array
    {
        return [
            'ID Venta',
            'Producto',
            'Almacén',
            'Cantidad',
            'Precio Unitario',
            'Subtotal',
            'Margen Ganancia',
            'Fecha',
        ];
    }

    public function title(): string
    {
        return 'Detalles Ventas';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D47A1']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            ],
            "A" . ($highestRow - $this->resumen_productos->count() - $this->top_productos->count() - 5) . ":H{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $dataRows = $event->sheet->getDelegate()->getHighestDataRow() - ($this->resumen_productos->count() + $this->top_productos->count() + 5);
                $topStartRow = $dataRows + 4 + $this->resumen_productos->count() + 2;
                $topEndRow = $topStartRow + $this->top_productos->count() - 1;
                $labels = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Detalles Ventas!\$B\${$topStartRow}:\$B\${$topEndRow}", null, $this->top_productos->count()), // Product names
                ];
                $values = [
                    new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Detalles Ventas!\$D\${$topStartRow}:\$D\${$topEndRow}", null, $this->top_productos->count()), // Quantities
                ];

                $dataSeries = new DataSeries(
                    DataSeries::TYPE_PIECHART,
                    null,
                    range(0, count($values) - 1),
                    $labels,
                    [],
                    $values
                );
                $plotArea = new PlotArea(new Layout(), [$dataSeries]);
                $chart = new Chart(
                    'Top 5 Productos',
                    new Title('Top 5 Productos Vendidos'),
                    new Legend(Legend::POSITION_RIGHT, null, false),
                    $plotArea
                );

                $chart->setTopLeftPosition('J2')->setBottomRightPosition('P15');
                $sheet->addChart($chart);
            },
        ];
    }
}

class PagosSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $tipo;
    protected $fechaInicio;
    protected $fechaFin;
    protected $data;
    protected $resumen_metodos;

    public function __construct($tipo, $fechaInicio, $fechaFin)
    {
        $this->tipo = $tipo;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    public function collection()
    {
        $query = Pago::query()
            ->join('ventas', 'pagos.idVenta', '=', 'ventas.idVenta')
            ->select(
                'pagos.idPago',
                'ventas.idVenta',
                'pagos.monto',
                'pagos.metodo',
                'pagos.fecha',
                'pagos.estado',
                'pagos.referencia_pago',
                DB::raw('COALESCE(pagos.deleted_at, "No Eliminado") as fecha_eliminacion')
            )
            ->whereNull('pagos.deleted_at');

        if ($this->tipo === 'diario') {
            $query->whereBetween('pagos.fecha', [$this->fechaInicio, Carbon::parse($this->fechaInicio)->endOfDay()->toDateTimeString()]);
        } elseif ($this->tipo === 'ventasfecha') {
            $query->whereBetween('pagos.fecha', [$this->fechaInicio, $this->fechaFin]);
        }

        $this->data = $query->get();

        $total_pagos = $this->data->count();
        $total_monto = $this->data->sum('monto');
        $pagos_aprobados = $this->data->where('estado', 'aprobado')->count();
        $pagos_pendientes = $this->data->where('estado', 'pendiente')->count();
        $pagos_rechazados = $this->data->where('estado', 'rechazado')->count();

        $mappedData = $this->data->map(function ($item) {
            return [
                'idPago' => $item->idPago,
                'idVenta' => $item->idVenta,
                'monto' => number_format($item->monto, 2),
                'metodo' => $item->metodo,
                'fecha' => $item->fecha->toDateTimeString(),
                'estado' => $item->estado,
                'referencia_pago' => $item->referencia_pago,
                'fecha_eliminacion' => $item->fecha_eliminacion,
            ];
        });

        $this->resumen_metodos = $this->data->groupBy('metodo')->map(function ($items) {
            return [
                'metodo' => $items->first()->metodo,
                'total_pagos' => $items->count(),
                'monto' => number_format($items->sum('monto'), 2),
            ];
        })->values();

        $metodo_summary = collect([new Collection(['Resumen por Método', '', '', '', '', '', '', ''])])
            ->concat($this->resumen_metodos->map(function ($item) {
                return new Collection(['', $item['metodo'], '', $item['total_pagos'], '', $item['monto'], '', '']);
            }));

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '']),
            new Collection(['Total Pagos', $total_pagos, '', '', '', '', '', '']),
            new Collection(['Total Monto', '', number_format($total_monto, 2), '', '', '', '', '']),
            new Collection(['Pagos Aprobados', '', '', $pagos_aprobados, '', '', '', '']),
            new Collection(['Pagos Pendientes', '', '', $pagos_pendientes, '', '', '', '']),
            new Collection(['Pagos Rechazados', '', '', $pagos_rechazados, '', '', '', '']),
        ]);

        return $mappedData->concat($summary)->concat($metodo_summary);
    }

    public function headings(): array
    {
        return [
            'ID Pago',
            'ID Venta',
            'Monto',
            'Método',
            'Fecha',
            'Estado',
            'Referencia Pago',
            'Fecha Eliminación',
        ];
    }

    public function title(): string
    {
        return 'Pagos';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $dataRows = $this->data->count();
        for ($i = 2; $i <= $dataRows + 1; $i++) {
            $estado = $sheet->getCell("F$i")->getValue();
            if ($estado === 'aprobado') {
                $sheet->getStyle("A$i:H$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFB2DFDB']],
                ]);
            } elseif ($estado === 'pendiente') {
                $sheet->getStyle("A$i:H$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE082']],
                ]);
            } elseif ($estado === 'rechazado') {
                $sheet->getStyle("A$i:H$i")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFCDD2']],
                ]);
            }
        }

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0D47A1']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
            ],
            "A" . ($highestRow - $this->resumen_metodos->count() - 5) . ":H{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
            ],
        ];
    }
}
