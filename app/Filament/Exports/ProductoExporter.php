<?php

namespace App\Filament\Exports;

use App\Models\Producto;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductoExporter extends Exporter
{
    protected static ?string $model = Producto::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('idProducto')->label('ID'),
            ExportColumn::make('nombre')->label('Nombre'),
            ExportColumn::make('codigo')->label('Código'),
            ExportColumn::make('descripcion')->label('Descripción'),
            ExportColumn::make('categoria.nombre')
                ->label('Categoría')
                ->formatStateUsing(fn($state, $record) => $record->categoria?->nombre ?? 'Sin Categoría'),
            ExportColumn::make('proveedor.nombre')
                ->label('Proveedor')
                ->formatStateUsing(fn($state, $record) => $record->proveedor?->nombre ?? 'Sin Proveedor'),
            ExportColumn::make('marca')->label('Marca'),
            ExportColumn::make('url_imagen')->label('Imagen URL'),
            ExportColumn::make('fecha_ingreso')
                ->label('Fecha de Ingreso')
                ->formatStateUsing(fn($state) => $state instanceof \DateTime ? $state->format('d/m/Y H:i') : ($state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : '-')),
            ExportColumn::make('fecha_actualizacion')
                ->label('Fecha Actualización')
                ->formatStateUsing(fn($state) => $state instanceof \DateTime ? $state->format('d/m/Y H:i') : ($state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : '-')),
            ExportColumn::make('estado')
                ->label('Estado')
                ->formatStateUsing(fn($state) => ucfirst($state)),
            ExportColumn::make('stockAlmacenes')
                ->label('Stock Detallado')
                ->formatStateUsing(function ($record) {
                    if ($record->stockAlmacenes->isEmpty()) {
                        return 'Sin Stock';
                    }
                    return $record->stockAlmacenes->map(function ($stock) {
                        $almacen = $stock->almacen->nombre ?? 'Sin Almacén';
                        $ubicacion = $stock->almacen->ubicacion ?? 'Sin Ubicación';
                        $cantidad = $stock->cantidad;
                        $costo = number_format($stock->costo_unitario, 2);
                        $venta = number_format($stock->precio_venta, 2);
                        return "{$almacen} ({$ubicacion}): {$cantidad} unidades, Costo: {$costo}, Venta: {$venta}";
                    })->implode(' | ');
                }),
            ExportColumn::make('promocion')
                ->label('Descuento')
                ->formatStateUsing(function ($record) {
                    $promocion = $record->promocion;
                    if ($promocion && $promocion->estado === 'activa' && $promocion->fecha_inicio <= now() && $promocion->fecha_fin >= now()) {
                        return "Descuento: {$promocion->descuento}% | Nombre: {$promocion->nombre} | Desde: {$promocion->fecha_inicio->format('d/m/Y')} | Hasta: {$promocion->fecha_fin->format('d/m/Y')}";
                    }
                    return 'Sin Descuento';
                }),
            ExportColumn::make('created_at')
                ->label('Creado')
                ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : '-'),
            ExportColumn::make('updated_at')
                ->label('Actualizado')
                ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : '-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de productos se completó con éxito. ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
