<?php

namespace App\Filament\Exports;

use App\Models\Compra;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CompraExporter extends Exporter
{
    protected static ?string $model = Compra::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('idCompra')
                ->label('ID Compra'),
            ExportColumn::make('proveedor.nombre')
                ->label('Proveedor'),
            ExportColumn::make('usuario.name')
                ->label('Registrado por'),
            ExportColumn::make('fecha')
                ->label('Fecha')
                ->formatStateUsing(fn($state) => $state ? $state->format('d/m/Y H:i') : '-'),
            ExportColumn::make('total')
                ->label('Total (Bs)')
                ->formatStateUsing(fn($state) => number_format($state, 2, '.', '')),
            ExportColumn::make('estado')
                ->label('Estado')
                ->formatStateUsing(fn($state) => ucfirst($state)),
            ExportColumn::make('detalles')
                ->label('Productos Comprados')
                ->getStateUsing(function ($record) {
                    return $record->detalles->map(function ($detalle) {
                        return "{$detalle->producto->nombre} (Cantidad: {$detalle->cantidad}, Costo Unitario: {$detalle->costo_unitario} Bs)";
                    })->implode('; ');
                }),
            ExportColumn::make('created_at')
                ->label('Creado')
                ->formatStateUsing(fn($state) => $state ? $state->format('d/m/Y H:i') : '-'),
            ExportColumn::make('updated_at')
                ->label('Actualizado')
                ->formatStateUsing(fn($state) => $state ? $state->format('d/m/Y H:i') : '-'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Tu exportación de compras ha finalizado y se exportaron con éxito' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
