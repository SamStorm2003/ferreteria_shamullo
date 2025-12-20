<?php

namespace App\Http\Controllers;

use App\Exports\ComprasExport;
use App\Exports\StockAlmacenExport;
use App\Exports\ProductosExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function generarReporteInventario(Request $request, $tipo)
    {
        $fechaActual = now()->format('Y-m-d');
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date|before_or_equal:fecha_fin',
            'fecha_fin' => 'required|date|before_or_equal:' . $fechaActual,
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $filename = match ($tipo) {
            'stock' => "reporte_stock_almacenes_{$fechaActual}.xlsx",
            'productos' => "reporte_productos_{$fechaActual}.xlsx",
            'comprasfecha' => "reporte_compras_{$fechaInicio}_al_{$fechaFin}.xlsx",
            default => null,
        };

        if (!$filename) {
            return redirect()->back()->with('error', 'Tipo de reporte no válido');
        }

        return match ($tipo) {
            'stock' => Excel::download(new StockAlmacenExport(), $filename),
            'productos' => Excel::download(new ProductosExport(), $filename),
            'comprasfecha' => Excel::download(new ComprasExport($tipo, $fechaInicio, $fechaFin), $filename),
        };
    }
}
