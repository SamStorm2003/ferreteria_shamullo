<?php

namespace App\Filament\Exports;

use App\Models\MovimientoInventario;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MovimientoInventarioExporter extends Exporter
{
    protected static ?string $model = MovimientoInventario::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('producto.descripcion')
                ->label('Producto'),
            ExportColumn::make('almacen.nombre')
                ->label('Almacén'),
            ExportColumn::make('tipo')
                ->label('Tipo de Movimiento')
                ->formatStateUsing(fn($state) => match ($state) {
                    'entrada' => 'Entrada',
                    'salida' => 'Salida',
                    'ajuste' => 'Ajuste',
                    default => $state,
                }),
            ExportColumn::make('cantidad')
                ->label('Cantidad'),
            ExportColumn::make('costo_unitario')
                ->label('Costo Unitario (Bs.)')
                ->formatStateUsing(fn($state) => 'Bs. ' . number_format($state, 2)),
            ExportColumn::make('fecha')
                ->label('Fecha')
                ->formatStateUsing(fn($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : '-'),
            ExportColumn::make('usuario.name')
                ->label('Responsable')
                ->formatStateUsing(fn($state) => $state ?? 'Sin responsable'),
            ExportColumn::make('motivo')
                ->label('Motivo')
                ->formatStateUsing(fn($state) => $state ?? '-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'La exportación de movimientos de inventario ha finalizado y se han exportado con éxito' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
