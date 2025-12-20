<?php

namespace App\Exports;

use App\Models\StockAlmacen;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Support\Facades\DB;

class StockAlmacenExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $almacens = StockAlmacen::query()
            ->join('almacens', 'stock_almacens.idAlmacen', '=', 'almacens.idAlmacen')
            ->select('almacens.idAlmacen', 'almacens.nombre')
            ->distinct()
            ->whereNull('stock_almacens.deleted_at')
            ->whereNull('almacens.deleted_at')
            ->get();

        $sheets = [];
        foreach ($almacens as $almacen) {
            $sheets[] = new class($almacen->idAlmacen, $almacen->nombre) implements FromCollection, WithHeadings, WithTitle, WithStyles {
                private $idAlmacen;
                private $nombreAlmacen;

                public function __construct(int $idAlmacen, string $nombreAlmacen)
                {
                    $this->idAlmacen = $idAlmacen;
                    $this->nombreAlmacen = $nombreAlmacen;
                }

                public function collection()
                {
                    $data = StockAlmacen::query()
                        ->join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                        ->join('almacens', 'stock_almacens.idAlmacen', '=', 'almacens.idAlmacen')
                        ->join('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                        ->leftJoin('proveedors', 'productos.idProveedor', '=', 'proveedors.idProveedor')
                        ->select(
                            'almacens.nombre as almacen',
                            'almacens.ubicacion as ubicacion',
                            'productos.nombre as producto',
                            'categorias.nombre as categoria',
                            DB::raw('COALESCE(proveedors.nombre, "Sin Proveedor") as proveedor'),
                            'stock_almacens.cantidad',
                            'stock_almacens.costo_unitario',
                            'stock_almacens.precio_venta',
                            DB::raw('IF(stock_almacens.cantidad = 0, "Eliminado", IF(stock_almacens.cantidad < 10, "Bajo Stock", "Normal")) as estado_stock'),
                            DB::raw('(stock_almacens.precio_venta - stock_almacens.costo_unitario) * stock_almacens.cantidad as ganancia'),
                            'productos.fecha_ingreso as fecha_ingreso',
                            'productos.codigo as codigo_producto'
                        )
                        ->where('stock_almacens.idAlmacen', $this->idAlmacen)
                        ->whereNull('stock_almacens.deleted_at')
                        ->whereNull('productos.deleted_at')
                        ->whereNull('almacens.deleted_at')
                        ->whereNull('categorias.deleted_at')
                        ->orderBy('productos.nombre')
                        ->get();

                    $total_stock = $data->sum('cantidad');
                    $total_ganancia = $data->sum(function ($item) {
                        return ($item->precio_venta - $item->costo_unitario) * $item->cantidad;
                    });
                    $total_costo = $data->sum(function ($item) {
                        return $item->costo_unitario * $item->cantidad;
                    });
                    $total_venta = $data->sum(function ($item) {
                        return $item->precio_venta * $item->cantidad;
                    });
                    $total_productos = $data->groupBy('idProducto')->count();

                    $summary = collect([
                        [],
                        ['Resumen', '', '', '', '', '', '', '', '', '', ''],
                        ['Total Stock Actual', $total_stock, '', '', '', '', '', '', '', '', ''],
                        ['Total Costo Inventario', '', '', '', '', number_format($total_costo, 2), '', '', '', '', ''],
                        ['Total Valor Venta', '', '', '', '', '', number_format($total_venta, 2), '', '', '', ''],
                        ['Total Ganancias Potenciales', '', '', '', '', '', '', number_format($total_ganancia, 2), '', '', ''],
                        ['Total Productos Únicos', $total_productos, '', '', '', '', '', '', '', '', '']
                    ]);

                    return $data->concat($summary);
                }

                public function headings(): array
                {
                    return [
                        'Almacén',
                        'Ubicación',
                        'Producto',
                        'Categoría',
                        'Proveedor',
                        'Cantidad',
                        'Costo Unitario',
                        'Precio Venta',
                        'Estado Stock',
                        'Ganancia Potencial',
                        'Fecha Ingreso',
                        'Código Producto'
                    ];
                }

                public function title(): string
                {
                    return $this->nombreAlmacen . ' ' . now()->format('Y-m-d');
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
                                'startColor' => ['argb' => 'FF2E7D32']
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
                        "A{$highestRow}:" . chr(ord($highestColumn) - 1) . "{$highestRow}" => [
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFE8F5E9']
                            ]
                        ]
                    ];
                }
            };
        }
        return $sheets;
    }
}
