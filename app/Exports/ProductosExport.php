<?php

namespace App\Exports;

use App\Models\Producto;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ProductosExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithEvents
{
    public function collection()
    {
        $data = Producto::query()
            ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
            ->leftJoin('proveedors', 'productos.idProveedor', '=', 'proveedors.idProveedor')
            ->leftJoin('stock_almacens', 'productos.idProducto', '=', 'stock_almacens.idProducto')
            ->leftJoin('almacens', 'stock_almacens.idAlmacen', '=', 'almacens.idAlmacen')
            ->select(
                'productos.nombre as producto',
                'productos.codigo as codigo',
                DB::raw('COALESCE(categorias.nombre, "Sin Categoría") as categoria'),
                DB::raw('COALESCE(proveedors.nombre, "Sin Proveedor") as proveedor'),
                'productos.descripcion',
                'productos.marca',
                'productos.url_imagen as imagen',
                'productos.fecha_ingreso',
                DB::raw('GROUP_CONCAT(DISTINCT CONCAT(almacens.nombre, " (", COALESCE(stock_almacens.cantidad, 0), ")")) as almacenes'),
                DB::raw('COALESCE(SUM(stock_almacens.cantidad), 0) as total_stock'),
                DB::raw('COALESCE(AVG(stock_almacens.costo_unitario), 0) as costo_promedio'),
                DB::raw('COALESCE(AVG(stock_almacens.precio_venta), 0) as precio_venta_promedio'),
                DB::raw('COALESCE(SUM((stock_almacens.precio_venta - stock_almacens.costo_unitario) * stock_almacens.cantidad), 0) as ganancia_potencial'),
                DB::raw('IF(COALESCE(SUM(stock_almacens.cantidad), 0) = 0, "Sin Stock", IF(COALESCE(SUM(stock_almacens.cantidad), 0) < 10, "Bajo Stock", "Normal")) as estado_stock'),
                DB::raw('IF(productos.deleted_at IS NOT NULL, "Eliminado", productos.estado) as estado'),
                DB::raw('COALESCE(productos.deleted_at, "No Eliminado") as fecha_eliminacion')
            )
            ->groupBy(
                'productos.idProducto',
                'productos.nombre',
                'productos.codigo',
                'categorias.nombre',
                'proveedors.nombre',
                'productos.descripcion',
                'productos.marca',
                'productos.url_imagen',
                'productos.fecha_ingreso',
                'productos.estado',
                'productos.deleted_at'
            )
            ->orderBy('productos.nombre')
            ->withTrashed()
            ->get();

        $total_stock = $data->sum('total_stock');
        $total_ganancia = $data->sum('ganancia_potencial');
        $total_costo = $data->sum(function ($item) {
            return $item['costo_promedio'] * $item['total_stock'];
        });
        $total_venta = $data->sum(function ($item) {
            return $item['precio_venta_promedio'] * $item['total_stock'];
        });
        $total_productos = $data->count();
        $bajo_stock = $data->where('estado_stock', 'Bajo Stock')->count();

        $summary = collect([
            new Collection(['Resumen', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Stock Actual', $total_stock, '', '', '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Costo Inventario', number_format($total_costo, 2), '', '', '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Valor Venta', '', '', number_format($total_venta, 2), '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Ganancias Potenciales', '', '', '', number_format($total_ganancia, 2), '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Total Productos', $total_productos, '', '', '', '', '', '', '', '', '', '', '', '', '', '']),
            new Collection(['Productos con Bajo Stock', $bajo_stock, '', '', '', '', '', '', '', '', '', '', '', '', '', ''])
        ]);

        $mappedData = $data->map(function ($item) {
            return [
                'producto' => $item['producto'] ?? '',
                'codigo' => $item['codigo'] ?? '',
                'categoria' => $item['categoria'] ?? '',
                'proveedor' => $item['proveedor'] ?? '',
                'descripcion' => $item['descripcion'] ?? '',
                'marca' => $item['marca'] ?? '',
                'imagen' => $item['imagen'] ?? '',
                'fecha_ingreso' => $item['fecha_ingreso'] ? $item['fecha_ingreso']->toDateTimeString() : '',
                'almacenes' => $item['almacenes'] ?? '',
                'total_stock' => $item['total_stock'] ?? 0,
                'costo_promedio' => number_format($item['costo_promedio'] ?? 0, 2),
                'precio_venta_promedio' => number_format($item['precio_venta_promedio'] ?? 0, 2),
                'ganancia_potencial' => number_format($item['ganancia_potencial'] ?? 0, 2),
                'estado_stock' => $item['estado_stock'] ?? '',
                'estado' => $item['estado'] ?? '',
                'fecha_eliminacion' => $item['fecha_eliminacion'] ?? ''
            ];
        });

        return $mappedData->concat($summary);
    }

    public function headings(): array
    {
        return [
            'Producto',
            'Código',
            'Categoría',
            'Proveedor',
            'Descripción',
            'Marca',
            'Imagen',
            'Fecha Ingreso',
            'Almacenes (Cantidad)',
            'Total Stock',
            'Costo Promedio',
            'Precio Venta Promedio',
            'Ganancia Potencial',
            'Estado Stock',
            'Estado',
            'Fecha Eliminación'
        ];
    }

    public function title(): string
    {
        return 'Reporte Productos ' . now()->format('Y-m-d');
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
            "A" . ($highestRow - 6) . ":" . chr(ord($highestColumn) - 1) . "{$highestRow}" => [
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
                    if ($index >= count($data) - 7) {
                        break;
                    }
                    if (isset($row['estado_stock']) && $row['estado_stock'] === 'Bajo Stock') {
                        $sheet->getStyle("A" . ($index + 2) . ":P" . ($index + 2))->applyFromArray([
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
