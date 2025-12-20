<?php

namespace App\Filament\Vendedor\Resources\VentaResource\Pages;

use App\Filament\Vendedor\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Envios;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;

class EditVenta extends EditRecord
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //  Actions\DeleteAction::make(),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = parent::getFormSchema();

        if (in_array($this->record->estado, ['pendiente', 'reservada', 'cancelada'])) {
            return [
                Forms\Components\Select::make('estado')
                    ->label('Estado de la Venta')
                    ->options([
                        'completada' => 'Completada',
                        'pendiente' => 'Pendiente',
                        'reservada' => 'Reservada',
                        'cancelada' => 'Cancelada',
                    ])
                    ->required(),
            ];
        }

        return $schema;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data) {
                $originalEstado = $record->estado;

                if ($data['estado'] === 'completada' && $originalEstado !== 'completada') {
                    $detalles = DetalleVenta::where('idVenta', $record->idVenta)->get();
                    if ($detalles->isEmpty()) {
                        Notification::make()
                            ->title('Error')
                            ->body('No se encontraron detalles de la venta #' . $record->idVenta)
                            ->danger()
                            ->send();
                        throw new \Exception('No se encontraron detalles de la venta');
                    }
                    $detallesToDelete = [];
                    $detallesToProcess = [];
                    foreach ($detalles as $detalle) {
                        $producto = Producto::find($detalle->idProducto);
                        if (!$producto) {
                            Notification::make()
                                ->title('Error')
                                ->body('El producto con ID ' . $detalle->idProducto . ' no existe.')
                                ->danger()
                                ->send();
                            throw new \Exception('Producto no encontrado');
                        }
                        $stock = StockAlmacen::where('idProducto', $detalle->idProducto)
                            ->where('idAlmacen', $detalle->idAlmacen)
                            ->lockForUpdate()
                            ->first();
                        if (!$stock || $stock->cantidad == 0) {
                            $detallesToDelete[] = $detalle;
                        } elseif ($stock->cantidad < $detalle->cantidad) {
                            Notification::make()
                                ->title('Error')
                                ->body('No hay suficiente stock para el producto ' . $producto->nombre . ' en el almacén. Stock actual: ' . $stock->cantidad)
                                ->danger()
                                ->send();
                            throw new \Exception('Stock insuficiente');
                        } else {
                            $detallesToProcess[] = $detalle;
                        }
                    }

                    foreach ($detallesToDelete as $detalle) {
                        $detalle->delete();
                        Notification::make()
                            ->title('Detalle eliminado')
                            ->body('El detalle del producto ' . $detalle->producto->nombre . ' se eliminó porque no hay stock disponible.')
                            ->warning()
                            ->send();
                    }
                    if (empty($detallesToProcess)) {
                        Notification::make()
                            ->title('Error')
                            ->body('No hay productos con stock suficiente para completar la venta.')
                            ->danger()
                            ->send();
                        throw new \Exception('Venta sin detalles válidos');
                    }
                    foreach ($detallesToProcess as $detalle) {
                        $stock = StockAlmacen::where('idProducto', $detalle->idProducto)
                            ->where('idAlmacen', $detalle->idAlmacen)
                            ->lockForUpdate()
                            ->first();
                        if ($stock->cantidad - $detalle->cantidad < 5) {
                            Notification::make()
                                ->title('Advertencia')
                                ->body('El stock del producto ' . $detalle->producto->nombre . ' quedará menor a 5 unidades.')
                                ->warning()
                                ->send();
                        }

                        $stock->cantidad -= $detalle->cantidad;
                        $stock->save();

                        MovimientoInventario::create([
                            'idProducto' => $detalle->idProducto,
                            'idAlmacen' => $detalle->idAlmacen,
                            'tipo' => 'salida',
                            'cantidad' => $detalle->cantidad,
                            'costo_unitario' => $stock->costo_unitario,
                            'fecha' => now(),
                            'idUsuario' => Auth::id(),
                            'motivo' => 'Venta completada #' . $record->idVenta,
                        ]);
                    }
                } elseif ($data['estado'] === 'cancelada' && $originalEstado !== 'cancelada') {
                    $detalles = DetalleVenta::where('idVenta', $record->idVenta)->get();
                    if ($detalles->isEmpty()) {
                        Notification::make()
                            ->title('Error')
                            ->body('No se encontraron detalles de la venta #' . $record->idVenta)
                            ->danger()
                            ->send();
                        throw new \Exception('No se encontraron detalles de la venta');
                    }

                    foreach ($detalles as $detalle) {
                        $producto = Producto::find($detalle->idProducto);
                        if (!$producto) {
                            Notification::make()
                                ->title('Error')
                                ->body('El producto con ID ' . $detalle->idProducto . ' no existe.')
                                ->danger()
                                ->send();
                            throw new \Exception('Producto no encontrado');
                        }

                        $stock = StockAlmacen::where('idProducto', $detalle->idProducto)
                            ->where('idAlmacen', $detalle->idAlmacen)
                            ->lockForUpdate()
                            ->first();

                        if ($stock) {
                            $stock->cantidad += $detalle->cantidad;
                            $stock->save();

                            MovimientoInventario::create([
                                'idProducto' => $detalle->idProducto,
                                'idAlmacen' => $detalle->idAlmacen,
                                'tipo' => 'entrada',
                                'cantidad' => $detalle->cantidad,
                                'costo_unitario' => $stock->costo_unitario,
                                'fecha' => now(),
                                'idUsuario' => Auth::id(),
                                'motivo' => 'Venta cancelada #' . $record->idVenta,
                            ]);
                        }
                    }
                }

                $record->update([
                    'estado' => $data['estado'],
                ]);

                Notification::make()
                    ->title('Éxito')
                    ->body('La venta ha sido actualizada correctamente.')
                    ->success()
                    ->send();

                return $record;
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Hubo un problema al actualizar la venta: ' . $e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }
}
