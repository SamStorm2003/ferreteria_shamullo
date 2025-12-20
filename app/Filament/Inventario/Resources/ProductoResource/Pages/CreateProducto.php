<?php

namespace App\Filament\Inventario\Resources\ProductoResource\Pages;

use App\Filament\Inventario\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateProducto extends CreateRecord
{
    protected static string $resource = ProductoResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['idAlmacen'])) {
            Notification::make()
                ->title('Error')
                ->body('No se puede crear el producto sin un almacén asignado.')
                ->danger()
                ->send();
            $this->halt();
        }
        if (!empty($data['url_imagen'])) {
            $bucketUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
            $data['url_imagen'] = $bucketUrl . $data['url_imagen'];
        }
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'productos_codigo_unique')) {
                $existingProduct = \App\Models\Producto::where('codigo', $data['codigo'])->first();
                if ($existingProduct) {
                    $idAlmacen = $data['idAlmacen'];
                    $existingStock = \App\Models\StockAlmacen::where('idProducto', $existingProduct->idProducto)
                        ->where('idAlmacen', $idAlmacen)
                        ->first();

                    if ($existingStock) {
                        $existingStock->update([
                            'cantidad' => $existingStock->cantidad + $data['cantidad'],
                            'costo_unitario' => $data['costo_unitario'],
                            'precio_venta' => $data['precio_venta'],
                        ]);
                    } else {
                        \App\Models\StockAlmacen::create([
                            'idProducto' => $existingProduct->idProducto,
                            'idAlmacen' => $idAlmacen,
                            'cantidad' => $data['cantidad'],
                            'costo_unitario' => $data['costo_unitario'],
                            'precio_venta' => $data['precio_venta'],
                        ]);
                    }

                    \App\Models\MovimientoInventario::create([
                        'idProducto' => $existingProduct->idProducto,
                        'idAlmacen' => $idAlmacen,
                        'tipo' => 'entrada',
                        'cantidad' => $data['cantidad'],
                        'costo_unitario' => $data['costo_unitario'],
                        'fecha' => now(),
                        'idUsuario' => Auth::id(),
                        'motivo' => 'Stock actualizado por código duplicado',
                    ]);

                    Notification::make()
                        ->title('Stock actualizado')
                        ->body('El código ya existe. Se actualizó el stock del producto: ' . $existingProduct->nombre)
                        ->success()
                        ->send();

                    $this->halt();
                }
            }
            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        try {
            $producto = $this->record;
            $data = $this->data;
            $idAlmacen = $data['idAlmacen'];
            $existingStock = \App\Models\StockAlmacen::where('idProducto', $producto->idProducto)
                ->where('idAlmacen', $idAlmacen)
                ->first();

            if ($existingStock) {
                $existingStock->update([
                    'cantidad' => $existingStock->cantidad + $data['cantidad'],
                    'costo_unitario' => $data['costo_unitario'],
                    'precio_venta' => $data['precio_venta'],
                ]);
            } else {
                \App\Models\StockAlmacen::create([
                    'idProducto' => $producto->idProducto,
                    'idAlmacen' => $idAlmacen,
                    'cantidad' => $data['cantidad'],
                    'costo_unitario' => $data['costo_unitario'],
                    'precio_venta' => $data['precio_venta'],
                ]);
            }
            \App\Models\MovimientoInventario::create([
                'idProducto' => $producto->idProducto,
                'idAlmacen' => $idAlmacen,
                'tipo' => 'entrada',
                'cantidad' => $data['cantidad'],
                'costo_unitario' => $data['costo_unitario'],
                'fecha' => now(),
                'idUsuario' => Auth::id(),
                'motivo' => 'Stock inicial al crear producto',
            ]);
            Notification::make()
                ->title('Producto creado')
                ->body('El producto y su stock inicial se registraron correctamente.')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Error al crear el producto')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }
}
