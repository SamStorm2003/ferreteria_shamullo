<?php

namespace App\Filament\Vendedor\Resources\VentaResource\Pages;

use App\Filament\Vendedor\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\ClienteExterno;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\StockAlmacen;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Envios;
use App\Models\Promocion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Almacen;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;

class CreateVenta extends CreateRecord
{
    protected static string $resource = VentaResource::class;

    protected function handleRecordCreation(array $data): Venta
    {
        try {
            return DB::transaction(function () use ($data) {
                if (empty($data['pagos'])) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se puede registrar una venta sin al menos un método de pago.')
                        ->danger()
                        ->send();
                    throw new \Exception('No se proporcionó un método de pago');
                }
                if (empty($data['detalles'])) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se puede registrar una venta sin al menos un producto.')
                        ->danger()
                        ->send();
                    throw new \Exception('No se proporcionó ningún producto');
                }
                $idClienteExterno = null;
                if ($data['cliente_type'] === 'externo') {
                    if (!empty($data['idClienteExterno'])) {
                        $idClienteExterno = $data['idClienteExterno'];
                        $clienteExterno = ClienteExterno::find($idClienteExterno);
                        if ($clienteExterno) {
                            $clienteExterno->update([
                                'nombre' => $data['nombre_cliente_externo'],
                                'documento_identidad' => $data['documento_identidad_externo'] ?? null,
                                'telefono' => $data['telefono_externo'] ?? null,
                                'correo' => $data['correo_externo'] ?? null,
                                'direccion' => $data['direccion_externo'] ?? null,
                            ]);
                        } else {
                            Notification::make()
                                ->title('Error')
                                ->body('Cliente externo no encontrado.')
                                ->danger()
                                ->send();
                            throw new \Exception('Cliente externo no encontrado');
                        }
                    } else {
                        $clienteExterno = ClienteExterno::create([
                            'nombre' => $data['nombre_cliente_externo'],
                            'documento_identidad' => $data['documento_identidad_externo'] ?? null,
                            'telefono' => $data['telefono_externo'] ?? null,
                            'correo' => $data['correo_externo'] ?? null,
                            'direccion' => $data['direccion_externo'] ?? null,
                        ]);
                        $idClienteExterno = $clienteExterno->idClienteExterno;
                    }
                }
                if ($data['cliente_type'] === 'registrado') {
                    $user = User::find($data['idUsuarioCliente']);
                    if ($user) {
                        $user->update([
                            'apellido' => $data['apellido'] ?? $user->apellido,
                            'telefono' => $data['telefono'] ?? $user->telefono,
                            'direccion' => $data['direccion'] ?? $user->direccion,
                            'ciudad' => $data['ciudad'] ?? $user->ciudad,
                            'documento_identidad' => $data['documento_identidad'] ?? $user->documento_identidad,
                            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? $user->fecha_nacimiento,
                        ]);
                    }
                }
                $venta = Venta::create([
                    'fecha' => now(),
                    'idUsuarioCliente' => $data['cliente_type'] === 'registrado' ? $data['idUsuarioCliente'] : null,
                    'idClienteExterno' => $idClienteExterno,
                    'idUsuarioVendedor' => Auth::id(),
                    'total' => 0,
                    'estado' => $data['estado'],
                    'tipo_entrega' => $data['tipo_entrega'],
                ]);
                if ($data['tipo_entrega'] === 'envio') {
                    Envios::create([
                        'idVenta' => $venta->idVenta,
                        'direccion_envio' => $data['envio']['direccion_envio'],
                        'metodo_envio' => $data['envio']['metodo_envio'],
                        'numero_seguimiento' => $data['envio']['numero_seguimiento'] ?? null,
                        'fecha_entrega_estimada' => $data['envio']['fecha_entrega_estimada'] ?? null,
                        'estado_envio' => 'pendiente',
                        'fecha_envio' => null,
                    ]);
                }
                $total = 0;
                foreach ($data['detalles'] as $detalle) {
                    if (empty($detalle['idProducto']) || empty($detalle['idAlmacen']) || empty($detalle['cantidad'])) {
                        Notification::make()
                            ->title('Error')
                            ->body('Todos los detalles del producto (producto, almacén y cantidad) son obligatorios.')
                            ->danger()
                            ->send();
                        throw new \Exception('Detalles del producto incompletos');
                    }
                    $stock = StockAlmacen::where('idProducto', $detalle['idProducto'])
                        ->where('idAlmacen', $detalle['idAlmacen'])
                        ->when($data['estado'] === 'completada', fn($query) => $query->lockForUpdate())
                        ->first();
                    if ($data['estado'] === 'completada') {
                        if (!$stock || $stock->cantidad < $detalle['cantidad']) {
                            Notification::make()
                                ->title('Error')
                                ->body('No hay suficiente stock para el producto ' . Producto::find($detalle['idProducto'])->nombre . '. Stock actual: ' . ($stock ? $stock->cantidad : 0))
                                ->danger()
                                ->send();
                            throw new \Exception('Stock insuficiente');
                        }

                        if ($stock->cantidad - $detalle['cantidad'] < 5) {
                            Notification::make()
                                ->title('Advertencia')
                                ->body('El stock del producto ' . Producto::find($detalle['idProducto'])->nombre . ' quedará menor a 5 unidades.')
                                ->warning()
                                ->send();
                        }
                    }
                    $promocion = Promocion::where('idProducto', $detalle['idProducto'])
                        ->where('estado', 'activa')
                        ->where('fecha_inicio', '<=', now())
                        ->where('fecha_fin', '>=', now())
                        ->first();
                    $precioUnitario = $stock->precio_venta;
                    if ($promocion) {
                        $precioUnitario = $precioUnitario * (1 - $promocion->descuento / 100);
                    }
                    DetalleVenta::create([
                        'idVenta' => $venta->idVenta,
                        'idProducto' => $detalle['idProducto'],
                        'idAlmacen' => $detalle['idAlmacen'],
                        'cantidad' => $detalle['cantidad'],
                        'precio_unitario' => $precioUnitario,
                    ]);
                    if ($data['estado'] === 'completada') {
                        $stock->cantidad -= $detalle['cantidad'];
                        $stock->save();
                        MovimientoInventario::create([
                            'idProducto' => $detalle['idProducto'],
                            'idAlmacen' => $detalle['idAlmacen'],
                            'tipo' => 'salida',
                            'cantidad' => $detalle['cantidad'],
                            'costo_unitario' => $stock->costo_unitario,
                            'fecha' => now(),
                            'idUsuario' => Auth::id(),
                            'motivo' => 'Venta #' . $venta->idVenta,
                        ]);
                    }

                    $total += $precioUnitario * $detalle['cantidad'];
                }
                $venta->total = $total;
                $venta->save();

                if (!empty($data['pagos'])) {
                    foreach ($data['pagos'] as $pago) {
                        if (empty($pago['metodo']) || empty($pago['estado'])) {
                            Notification::make()
                                ->title('Error')
                                ->body('El método de pago y el estado son obligatorios para cada pago.')
                                ->danger()
                                ->send();
                            throw new \Exception('Datos de pago incompletos');
                        }
                        Pago::create([
                            'idVenta' => $venta->idVenta,
                            'monto' => $total,
                            'metodo' => $pago['metodo'],
                            'fecha' => now(),
                            'estado' => $pago['estado'] ?? 'aprobado',
                            'referencia_pago' => $pago['referencia_pago'] ?? null,
                        ]);
                    }
                }
                Notification::make()
                    ->title('Éxito')
                    ->body('La venta ha sido registrada correctamente.')
                    ->success()
                    ->send();

                return $venta;
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Hubo un problema al registrar la venta: ' . $e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        if ($record->estado === 'completada') {
            $downloadUrl = route('download.invoice', ['idVenta' => $record->idVenta]);
            Notification::make()
                ->title('Factura generada')
                ->body("La venta se creó correctamente. <a href=\"{$downloadUrl}\" target=\"_blank\" class=\"underline text-blue-600\">Descargar factura</a>")
                ->success()
                ->persistent()
                ->send();
        }
    }
}
