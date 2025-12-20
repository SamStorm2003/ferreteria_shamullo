<?php

namespace App\Filament\Exports;

use App\Models\Venta;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class VentasExporter extends Exporter
{
    protected static ?string $model = Venta::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('idVenta')
                ->label('ID Venta'),
            ExportColumn::make('fecha')
                ->label('Fecha')
                ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y H:i')),
            ExportColumn::make('cliente_nombre')
                ->label('Cliente')
                ->formatStateUsing(function ($record) {
                    if ($record->clienteUsuario) {
                        return $record->clienteUsuario->name . ' (' . ($record->clienteUsuario->email ?? 'Sin correo') . ')';
                    }
                    if ($record->clienteExterno) {
                        return $record->clienteExterno->nombre . ' (' . ($record->clienteExterno->correo ?? 'Sin correo') . ')';
                    }
                    return 'Sin cliente';
                }),
            ExportColumn::make('cliente_contacto')
                ->label('Contacto Cliente')
                ->formatStateUsing(function ($record) {
                    if ($record->clienteUsuario) {
                        return 'Tel: ' . ($record->clienteUsuario->telefono ?? 'N/A');
                    }
                    if ($record->clienteExterno) {
                        return 'Tel: ' . ($record->clienteExterno->telefono ?? 'N/A');
                    }
                    return 'N/A';
                }),
            ExportColumn::make('productos')
                ->label('Productos')
                ->formatStateUsing(function ($record) {
                    $detalles = $record->detalles;
                    if ($detalles->isEmpty()) {
                        return 'Sin productos';
                    }
                    return $detalles->map(function ($detalle) {
                        $producto = \App\Models\Producto::find($detalle->idProducto);
                        $almacen = \App\Models\Almacen::find($detalle->idAlmacen);
                        return "{$producto?->nombre} (Cant: {$detalle->cantidad}, Precio: " . number_format($detalle->precio_unitario, 2) . " Bs, Almacén: {$almacen?->nombre})";
                    })->join('; ');
                }),
            ExportColumn::make('pagos')
                ->label('Pagos')
                ->formatStateUsing(fn($record) => $record->pagos->map(function ($pago) {
                    return number_format($pago->monto, 2) . ' Bs (' . ucfirst($pago->metodo) . ', ' . ucfirst($pago->estado) . ', Ref: ' . ($pago->referencia_pago ?? 'N/A') . ')';
                })->join('; ')),
            ExportColumn::make('direccion_envio')
                ->label('Dirección Envío')
                ->formatStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? 'Recogida' : ($record->envio?->direccion_envio ?? 'N/A')),
            ExportColumn::make('metodo_envio')
                ->label('Método Envío')
                ->formatStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ucfirst($record->envio?->metodo_envio ?? 'N/A')),
            ExportColumn::make('numero_seguimiento')
                ->label('Tracking')
                ->formatStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ($record->envio?->numero_seguimiento ?? 'N/A')),
            ExportColumn::make('fecha_entrega_estimada')
                ->label('Est. Entrega')
                ->formatStateUsing(function ($record) {
                    if ($record->tipo_entrega === 'recogida') return '—';
                    return $record->envio?->fecha_entrega_estimada
                        ? Carbon::parse($record->envio->fecha_entrega_estimada)->format('d/m/Y')
                        : 'N/A';
                }),
            ExportColumn::make('estado_envio')
                ->label('Estado Envío')
                ->formatStateUsing(fn($record) => $record->tipo_entrega === 'recogida' ? '—' : ucfirst($record->envio?->estado_envio ?? 'N/A')),
            ExportColumn::make('vendedor')
                ->label('Vendedor')
                ->formatStateUsing(function ($record) {
                    return $record->vendedor ? $record->vendedor->name . ' (' . ($record->vendedor->email ?? 'N/A') . ')' : 'Sin vendedor';
                }),
            ExportColumn::make('total')
                ->label('Total')
                ->formatStateUsing(fn($state) => number_format($state, 2) . ' BOB'),
            ExportColumn::make('estado')
                ->label('Estado')
                ->formatStateUsing(fn($state) => ucfirst($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your ventas export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
