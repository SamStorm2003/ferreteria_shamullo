<?php

namespace App\Filament\Exports;

use App\Models\Proveedor;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProveedorExporter extends Exporter
{
    protected static ?string $model = Proveedor::class;

       public static function getColumns(): array
    {
        return [
            ExportColumn::make('idProveedor')->label('ID'),
            ExportColumn::make('nombre')->label('Nombre'),
            ExportColumn::make('contacto')->label('Contacto'),
            ExportColumn::make('telefono')->label('Teléfono'),
            ExportColumn::make('correo')->label('Correo'),
            ExportColumn::make('direccion')->label('Dirección'),
            ExportColumn::make('estado')
                ->label('Estado')
                ->formatStateUsing(fn($record) => $record->estado === 'activo' ? 'Activo' : 'Inactivo'),
            ExportColumn::make('fecha_registro')->label('Fecha de Registro'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'La exportación de proveedores ha finalizado con éxito ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
