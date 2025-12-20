<?php

namespace App\Exports;

use App\Models\Producto;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\MovimientoInventario;
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

class ComprasExport implements WithMultipleSheets
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
            'Detalles Productos' => new ProductosSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
            'Resumen Compras' => new ComprasSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
            'Movimientos Inventario' => new MovimientosSheet($this->tipo, $this->fechaInicio, $this->fechaFin),
        ];
    }
}

class ProductosSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
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
        $query = Producto::query()
            ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
            ->leftJoin('proveedors', 'productos.idProveedor', '=', 'proveedors.idProveedor')
            ->leftJoin('stock_almacens', function ($join) {
                $join->on('productos.idProducto', '=', 'stock_almacens.idProducto')
                    ->whereNull('stock_almacens.deleted_at');
            })
            ->select(
                'productos.idProducto',
                'productos.nombre as producto',
                'productos.codigo as codigo',
                DB::raw('COALESCE(categorias.nombre, "Sin Categoría") as categoria'),
                'productos.marca',
                'productos.descripcion',
                'productos.url_imagen as imagen',
                'productos.fecha_ingreso',
                DB::raw('COALESCE(proveedors.nombre, "Sin Proveedor") as proveedor'),
                DB::raw('COALESCE(SUM(stock_almacens.cantidad), 0) as stock_actual'),
                DB::raw('IF(COALESCE(SUM(stock_almacens.cantidad), 0) < 10, "Bajo Stock", "Normal") as estado_stock'),
                DB::raw('IF(productos.deleted_at IS NOT NULL, "Eliminado", productos.estado) as estado_producto'),
                DB::raw('COALESCE(productos.deleted_at, "No Eliminado") as fecha_eliminacion_producto')
            )
            ->groupBy(
                'productos.idProducto',
                'productos.nombre',
                'productos.codigo',
                'categorias.nombre',
                'productos.marca',
                'productos.descripcion',
                'productos.url_imagen',
                'productos.fecha_ingreso',
                'proveedors.nombre',
                'productos.estado',
                'productos.deleted_at'
            )
            ->orderBy('productos.nombre');

        $data = $query->get();

        $total_productos = $data->count();
        $bajo_stock = $data->where('estado_stock', 'Bajo Stock')->count();
        $productos_eliminados = $data->where('estado_producto', 'Eliminado')->count();

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Productos', $total_productos, '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Productos con Bajo Stock', '', $bajo_stock, '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Productos Eliminados', '', '', $productos_eliminados, '', '', '', '', '', '', '', '', ''])
        ]);

        $mappedData = $data->map(function ($item) {
            return [
                'idProducto' => $item['idProducto'] ?? '',
                'producto' => $item['producto'] ?? '',
                'codigo' => $item['codigo'] ?? '',
                'categoria' => $item['categoria'] ?? '',
                'marca' => $item['marca'] ?? '',
                'descripcion' => $item['descripcion'] ?? '',
                'imagen' => $item['imagen'] ?? '',
                'fecha_ingreso' => $item['fecha_ingreso'] ? $item['fecha_ingreso']->toDateTimeString() : '',
                'proveedor' => $item['proveedor'] ?? '',
                'stock_actual' => $item['stock_actual'] ?? 0,
                'estado_stock' => $item['estado_stock'] ?? '',
                'estado_producto' => $item['estado_producto'] ?? '',
                'fecha_eliminacion_producto' => $item['fecha_eliminacion_producto'] ?? ''
            ];
        });

        return $mappedData->concat($summary);
    }

    public function headings(): array
    {
        return [
            'ID Producto',
            'Producto',
            'Código',
            'Categoría',
            'Marca',
            'Descripción',
            'Imagen',
            'Fecha Ingreso',
            'Proveedor',
            'Stock Actual',
            'Estado Stock',
            'Estado Producto',
            'Fecha Eliminación Producto'
        ];
    }

    public function title(): string
    {
        return 'Detalles Productos';
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
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A" . ($highestRow - 3) . ":" . chr(ord($highestColumn) - 1) . "{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFBBDEFB']
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $data = $this->collection()->toArray();

                foreach ($data as $index => $row) {
                    if ($index >= count($data) - 4) {
                        break; 
                    }
                    if (isset($row['estado_stock']) && $row['estado_stock'] === 'Bajo Stock') {
                        $sheet->getStyle("A" . ($index + 2) . ":M" . ($index + 2))->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFF9C4']
                            ]
                        ]);
                    }
                }
            }
        ];
    }
}

class ComprasSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
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
        $query = Compra::query()
            ->join('proveedors', 'compras.idProveedor', '=', 'proveedors.idProveedor')
            ->join('almacens', 'compras.idAlmacen', '=', 'almacens.idAlmacen')
            ->leftJoin('users', 'compras.idUsuario', '=', 'users.id')
            ->select(
                'compras.idCompra',
                'proveedors.nombre as proveedor',
                'almacens.nombre as almacen',
                DB::raw('COALESCE(users.name, "Sin Usuario") as usuario'),
                'compras.fecha',
                'compras.total',
                'compras.estado',
                DB::raw('COALESCE(compras.deleted_at, "No Eliminado") as fecha_eliminacion')
            )
            ->whereNull('compras.deleted_at');
        if ($this->tipo === 'diario') {
            $query->whereBetween('compras.fecha', [$this->fechaInicio, Carbon::parse($this->fechaInicio)->endOfDay()->toDateTimeString()]);
        } elseif ($this->tipo === 'comprasfecha') {
            $query->whereBetween('compras.fecha', [$this->fechaInicio, $this->fechaFin]);
        }

        $compras = $query->get();

        $data = collect();
        $total_compras = 0;
        $total_monto = 0;

        foreach ($compras as $compra) {
            $detalles = DetalleCompra::where('idCompra', $compra->idCompra)
                ->join('productos', 'detalle_compras.idProducto', '=', 'productos.idProducto')
                ->select(
                    'productos.nombre as producto',
                    'detalle_compras.cantidad',
                    'detalle_compras.costo_unitario',
                    DB::raw('detalle_compras.cantidad * detalle_compras.costo_unitario as subtotal')
                )
                ->whereNull('detalle_compras.deleted_at')
                ->get();

            $data->push([
                'idCompra' => $compra->idCompra,
                'proveedor' => $compra->proveedor,
                'almacen' => $compra->almacen,
                'usuario' => $compra->usuario,
                'fecha' => $compra->fecha ? $compra->fecha->toDateTimeString() : 'Sin Fecha',
                'total' => number_format($compra->total, 2),
                'estado' => $compra->estado,
                'fecha_eliminacion' => $compra->fecha_eliminacion,
                'producto' => '',
                'cantidad' => '',
                'costo_unitario' => '',
                'subtotal' => ''
            ]);

            foreach ($detalles as $detalle) {
                $data->push([
                    'idCompra' => '',
                    'proveedor' => '',
                    'almacen' => '',
                    'usuario' => '',
                    'fecha' => '',
                    'total' => '',
                    'estado' => '',
                    'fecha_eliminacion' => '',
                    'producto' => $detalle->producto,
                    'cantidad' => $detalle->cantidad,
                    'costo_unitario' => number_format($detalle->costo_unitario, 2),
                    'subtotal' => number_format($detalle->subtotal, 2)
                ]);
            }

            $total_compras++;
            $total_monto += $compra->total;
        }

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Compras', $total_compras, '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Monto', '', number_format($total_monto, 2), '', '', '', '', '', '', '', '', ''])
        ]);

        return $data->concat($summary);
    }

    public function headings(): array
    {
        return [
            'ID Compra',
            'Proveedor',
            'Almacén',
            'Usuario',
            'Fecha',
            'Total',
            'Estado',
            'Fecha Eliminación',
            'Producto',
            'Cantidad',
            'Costo Unitario',
            'Subtotal'
        ];
    }

    public function title(): string
    {
        return 'Resumen Compras';
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
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A" . ($highestRow - 2) . ":" . chr(ord($highestColumn) - 1) . "{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFBBDEFB']
                ]
            ]
        ];
    }
}

class MovimientosSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
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
        $query = MovimientoInventario::query()
            ->join('productos', 'movimiento_inventarios.idProducto', '=', 'productos.idProducto')
            ->join('almacens', 'movimiento_inventarios.idAlmacen', '=', 'almacens.idAlmacen')
            ->leftJoin('users', 'movimiento_inventarios.idUsuario', '=', 'users.id')
            ->select(
                'movimiento_inventarios.idMovimiento',
                'productos.nombre as producto',
                'almacens.nombre as almacen',
                DB::raw('COALESCE(users.name, "Sin Usuario") as usuario'),
                'movimiento_inventarios.tipo',
                'movimiento_inventarios.cantidad',
                'movimiento_inventarios.costo_unitario',
                'movimiento_inventarios.fecha',
                'movimiento_inventarios.motivo',
                DB::raw('COALESCE(movimiento_inventarios.deleted_at, "No Eliminado") as fecha_eliminacion')
            )
            ->whereNull('movimiento_inventarios.deleted_at');

        if ($this->tipo === 'diario') {
            $query->whereBetween('movimiento_inventarios.fecha', [$this->fechaInicio, Carbon::parse($this->fechaInicio)->endOfDay()->toDateTimeString()]);
        } elseif ($this->tipo === 'comprasfecha') {
            $query->whereBetween('movimiento_inventarios.fecha', [$this->fechaInicio, $this->fechaFin]);
        }

        $data = $query->get();

        if ($data->isEmpty()) {
            $compras = Compra::whereNull('deleted_at')
                ->whereBetween('fecha', [$this->fechaInicio, $this->fechaFin])
                ->get();

            foreach ($compras as $compra) {
                $detalles = DetalleCompra::where('idCompra', $compra->idCompra)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($detalles as $detalle) {

                    $exists = MovimientoInventario::where('idProducto', $detalle->idProducto)
                        ->where('idAlmacen', $compra->idAlmacen)
                        ->where('tipo', 'entrada')
                        ->where('fecha', $compra->fecha)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$exists) {
                        MovimientoInventario::create([
                            'idProducto' => $detalle->idProducto,
                            'idAlmacen' => $compra->idAlmacen,
                            'tipo' => 'entrada',
                            'cantidad' => $detalle->cantidad,
                            'costo_unitario' => $detalle->costo_unitario,
                            'fecha' => $compra->fecha,
                            'idUsuario' => $compra->idUsuario,
                            'motivo' => 'Compra #' . $compra->idCompra,
                        ]);
                    }
                }
            }

            $data = $query->get();
        }

        $total_movimientos = $data->count();
        $total_entradas = $data->where('tipo', 'entrada')->count();
        $total_salidas = $data->where('tipo', 'salida')->count();
        $total_ajustes = $data->where('tipo', 'ajuste')->count();

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Movimientos', $total_movimientos, '', '', '', '', '', '', '', '']),
            new Collection(['Total Entradas', '', $total_entradas, '', '', '', '', '', '', '']),
            new Collection(['Total Salidas', '', '', $total_salidas, '', '', '', '', '', '']),
            new Collection(['Total Ajustes', '', '', '', $total_ajustes, '', '', '', '', ''])
        ]);

        return $data->map(function ($item) {
            return [
                'idMovimiento' => $item['idMovimiento'],
                'producto' => $item['producto'],
                'almacen' => $item['almacen'],
                'usuario' => $item['usuario'],
                'tipo' => $item['tipo'],
                'cantidad' => $item['cantidad'],
                'costo_unitario' => number_format($item['costo_unitario'], 2),
                'fecha' => $item['fecha'] ? $item['fecha']->toDateTimeString() : 'Sin Fecha',
                'motivo' => $item['motivo'] ?? 'Sin Motivo',
                'fecha_eliminacion' => $item['fecha_eliminacion']
            ];
        })->concat($summary);
    }

    public function headings(): array
    {
        return [
            'ID Movimiento',
            'Producto',
            'Almacén',
            'Usuario',
            'Tipo',
            'Cantidad',
            'Costo Unitario',
            'Fecha',
            'Motivo',
            'Fecha Eliminación'
        ];
    }

    public function title(): string
    {
        return 'Movimientos Inventario';
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
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A2:{$highestColumn}{$highestRow}" => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ],
            "A" . ($highestRow - 4) . ":" . chr(ord($highestColumn) - 1) . "{$highestRow}" => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFBBDEFB']
                ]
            ]
        ];
    }
}
